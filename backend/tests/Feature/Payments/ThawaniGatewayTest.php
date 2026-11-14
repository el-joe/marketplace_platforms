<?php

namespace Tests\Feature\Payments;

use App\Models\CartItem;
use App\Models\CountryPaymentGateway;
use App\Models\Order;
use App\Models\PaymentGateway;
use App\Services\Customer\CartService;
use App\Services\Payments\ThawaniGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * Thawani checkout as its activation checklist and API require: customer details on the session
 * metadata, webhooks accepted only with a valid `thawani-signature` (HMAC-SHA256 of "{body}-{timestamp}")
 * and their status confirmed against Thawani's API, and refunds made against the session's payment.
 */
class ThawaniGatewayTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'whsec_test_secret';

    private const SESSION_ID = 'checkout_TEST123';

    private const API = 'https://uatcheckout.thawani.om/api/v1';

    private MarketplaceScenario $scenario;

    private CountryPaymentGateway $thawani;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->scenario = MarketplaceScenario::make()->build();
        $this->scenario->country->update(['site_code' => 'om-'.Str::lower(Str::random(6))]);

        $gateway = PaymentGateway::create([
            'code' => 'thawani',
            'type' => 'redirect',
            'name' => 'Thawani',
            'required_fields' => [['key' => 'secret_key', 'label' => 'Secret Key'], ['key' => 'publishable_key', 'label' => 'Publishable Key']],
            'supports_webhook' => true,
            'supports_refund' => true,
            'is_active' => true,
        ]);
        $this->thawani = CountryPaymentGateway::create([
            'country_id' => $this->scenario->country->id,
            'gateway_id' => $gateway->id,
            'display_name_en' => 'Card (Thawani)',
            'display_name_ar' => 'بطاقة (ثواني)',
            'is_active' => true,
            'environment' => 'sandbox',
            'fee_pct' => 0,
            'fee_fixed' => 0,
        ]);
        $this->thawani->setCredentials(['secret_key' => 'sk_test', 'publishable_key' => 'pk_test']);
        $this->thawani->setWebhookSecret(self::WEBHOOK_SECRET);
    }

    private function fakeThawani(string $sessionPaymentStatus = 'unpaid'): void
    {
        Http::fake([
            self::API.'/checkout/session/'.self::SESSION_ID => Http::response(['data' => [
                'session_id' => self::SESSION_ID,
                'payment_status' => $sessionPaymentStatus,
                'invoice' => 'INV-1',
                'total_amount' => 0,
            ]]),
            self::API.'/checkout/session' => Http::response(['data' => ['session_id' => self::SESSION_ID]]),
            self::API.'/payments*' => Http::response(['data' => [['payment_id' => 'pay_1', 'status' => 'successful', 'amount' => 5000]]]),
            self::API.'/refunds' => Http::response(['data' => ['refund_id' => 'ref_1']]),
        ]);
    }

    private function placeThawaniOrder(): Order
    {
        $this->actingAs($this->scenario->customer, 'customer');
        $cart = app(CartService::class)->getOrCreateCart($this->scenario->customer, $this->scenario->country->id, $this->scenario->country->currency_code);
        CartItem::create([
            'cart_id' => $cart->id,
            'vendor_listing_id' => $this->scenario->vendorListingFbp->id,
            'quantity' => 1,
            'unit_price' => (int) $this->scenario->vendorListingFbp->getRawOriginal('price'),
            'added_at' => now(),
        ]);

        $response = $this->postJson("/api/customer/v1/{$this->scenario->country->site_code}/checkout/place-order", [
            'address_id' => $this->scenario->customerAddress->id,
            'country_payment_gateway_id' => $this->thawani->id,
            'idempotency_key' => (string) Str::uuid(),
        ])->assertCreated();

        return Order::where('order_number', $response->json('data.order.order_number') ?? $response->json('data.order_number'))->firstOrFail();
    }

    /** @param array<string, string> $headers */
    private function postWebhook(array $payload, array $headers): TestResponse
    {
        $body = json_encode($payload);

        return $this->call('POST', '/webhooks/payment/thawani', [], [], [], array_merge(
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            collect($headers)->mapWithKeys(fn ($value, $name) => ['HTTP_'.strtoupper(str_replace('-', '_', $name)) => $value])->all(),
        ), $body);
    }

    /** @return array<string, string> */
    private function signedHeaders(array $payload, string $secret = self::WEBHOOK_SECRET): array
    {
        $timestamp = (string) now()->timestamp;

        return [
            'thawani-timestamp' => $timestamp,
            'thawani-signature' => hash_hmac('sha256', json_encode($payload).'-'.$timestamp, $secret),
        ];
    }

    public function test_the_checkout_session_carries_the_customer_name_contact_number_and_email(): void
    {
        $this->fakeThawani();

        $order = $this->placeThawaniOrder();

        Http::assertSent(function (Request $request) use ($order) {
            if ($request->method() !== 'POST' || ! str_ends_with($request->url(), '/checkout/session')) {
                return false;
            }

            $metadata = $request['metadata'];

            return $request['client_reference_id'] === $order->order_number
                && $metadata['customer_name'] === $this->scenario->customer->name
                && $metadata['customer_email'] === $this->scenario->customer->email
                && ($metadata['customer_phone'] ?? null) === $this->scenario->customer->phone;
        });
    }

    public function test_an_unsigned_or_forged_webhook_is_refused_and_the_order_stays_unpaid(): void
    {
        $this->fakeThawani('paid');
        $order = $this->placeThawaniOrder();
        $payload = ['data' => ['session_id' => self::SESSION_ID, 'client_reference_id' => $order->order_number, 'payment_status' => 'paid']];

        $this->postWebhook($payload, [])->assertStatus(401);
        $this->postWebhook($payload, $this->signedHeaders($payload, 'wrong-secret'))->assertStatus(401);

        $this->assertSame('pending', $order->fresh()->payment_status->value);
    }

    public function test_a_signed_webhook_cannot_mark_an_order_paid_that_thawani_reports_unpaid(): void
    {
        $this->fakeThawani('unpaid');
        $order = $this->placeThawaniOrder();
        $payload = ['data' => ['session_id' => self::SESSION_ID, 'client_reference_id' => $order->order_number, 'payment_status' => 'paid']];

        $this->postWebhook($payload, $this->signedHeaders($payload))->assertOk();

        $this->assertSame('pending', $order->fresh()->payment_status->value);
    }

    public function test_a_signed_webhook_for_a_paid_session_captures_the_order(): void
    {
        $this->fakeThawani('paid');
        $order = $this->placeThawaniOrder();
        $payload = ['data' => ['session_id' => self::SESSION_ID, 'client_reference_id' => $order->order_number, 'payment_status' => 'paid']];

        $this->postWebhook($payload, $this->signedHeaders($payload))->assertOk();

        $this->assertSame('captured', $order->fresh()->payment_status->value);
    }

    public function test_a_refund_is_made_against_the_sessions_payment(): void
    {
        $this->fakeThawani('paid');

        $result = (new ThawaniGateway($this->thawani->fresh()))->refund(self::SESSION_ID, 50, 'Customer request');

        $this->assertTrue($result->success);
        $this->assertSame('ref_1', $result->refundTransactionId);
        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && str_ends_with($request->url(), '/refunds')
            && $request['payment_id'] === 'pay_1');
    }

    public function test_a_partial_refund_is_refused_because_thawani_refunds_the_whole_payment(): void
    {
        $this->fakeThawani('paid');

        $result = (new ThawaniGateway($this->thawani->fresh()))->refund(self::SESSION_ID, 10, 'Customer request');

        $this->assertFalse($result->success);
        Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/refunds'));
    }
}
