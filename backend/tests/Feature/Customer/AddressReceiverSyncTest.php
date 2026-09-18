<?php

namespace Tests\Feature\Customer;

use App\Models\CartItem;
use App\Models\CustomerReceiver;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * Issue 2 (docs/plans/cart-warranty-receiver-coupon-fixes.md): creating an
 * address should also create a matching CustomerReceiver so it becomes
 * selectable during checkout, without spamming duplicate receivers and
 * without disturbing existing receivers' default flags.
 */
class AddressReceiverSyncTest extends TestCase
{
    use RefreshDatabase;

    private function buildScenario(): MarketplaceScenario
    {
        $scenario = MarketplaceScenario::make()->build();
        $scenario->country->update(['site_code' => 'ae-'.Str::lower(Str::random(6))]);

        return $scenario;
    }

    private function addressPayload(array $overrides = []): array
    {
        return array_merge([
            'recipient_name' => 'Jane Doe',
            'recipient_phone' => '+971500000001',
            'street_address' => '456 New Street',
            'address_type' => 'home',
        ], $overrides);
    }

    public function test_creating_address_creates_exactly_one_matching_receiver(): void
    {
        $scenario = $this->buildScenario();
        $this->actingAs($scenario->customer, 'customer');

        $response = $this->postJson(
            "/api/customer/v1/{$scenario->country->site_code}/addresses",
            $this->addressPayload()
        );

        $response->assertStatus(201);

        $matching = CustomerReceiver::where('customer_id', $scenario->customer->id)
            ->where('name', 'Jane Doe')
            ->where('phone', '+971500000001')
            ->get();

        $this->assertCount(1, $matching);
    }

    public function test_creating_a_second_identical_address_does_not_duplicate_the_receiver(): void
    {
        $scenario = $this->buildScenario();
        $this->actingAs($scenario->customer, 'customer');

        $payload = $this->addressPayload();

        $this->postJson("/api/customer/v1/{$scenario->country->site_code}/addresses", $payload)
            ->assertStatus(201);
        $this->postJson("/api/customer/v1/{$scenario->country->site_code}/addresses", $payload)
            ->assertStatus(201);

        $matching = CustomerReceiver::where('customer_id', $scenario->customer->id)
            ->where('name', 'Jane Doe')
            ->where('phone', '+971500000001')
            ->get();

        $this->assertCount(1, $matching);
    }

    public function test_creating_a_second_differently_named_address_does_not_disturb_first_receivers_default_flag(): void
    {
        $scenario = $this->buildScenario();
        $this->actingAs($scenario->customer, 'customer');

        // Scenario builder already gave this customer an address with
        // recipient_name === $scenario->customer->name, but no receiver yet
        // (that address was inserted directly via Address::create(), not
        // through AddressController::store()). Creating our first address
        // through the endpoint should become the customer's default
        // receiver (first-ever receiver).
        $this->postJson(
            "/api/customer/v1/{$scenario->country->site_code}/addresses",
            $this->addressPayload(['recipient_name' => 'Jane Doe', 'recipient_phone' => '+971500000001'])
        )->assertStatus(201);

        $firstReceiver = CustomerReceiver::where('customer_id', $scenario->customer->id)
            ->where('name', 'Jane Doe')
            ->firstOrFail();
        $this->assertTrue($firstReceiver->is_default);

        // A second, differently-named address must NOT flip the first
        // receiver's default flag or create it as default itself.
        $this->postJson(
            "/api/customer/v1/{$scenario->country->site_code}/addresses",
            $this->addressPayload(['recipient_name' => 'John Smith', 'recipient_phone' => '+971500000002'])
        )->assertStatus(201);

        $firstReceiver->refresh();
        $this->assertTrue($firstReceiver->is_default, 'creating a second address must not un-default the first receiver');

        $secondReceiver = CustomerReceiver::where('customer_id', $scenario->customer->id)
            ->where('name', 'John Smith')
            ->firstOrFail();
        $this->assertFalse($secondReceiver->is_default);
    }

    public function test_checkout_can_pass_receiver_id_and_resolve_receiver_picks_it_up(): void
    {
        $scenario = $this->buildScenario();
        $this->actingAs($scenario->customer, 'customer');

        $receiver = CustomerReceiver::create([
            'customer_id' => $scenario->customer->id,
            'name' => 'Alternate Receiver',
            'phone' => '+971500009999',
            'is_default' => false,
        ]);

        $cart = app(\App\Services\Customer\CartService::class)->getOrCreateCart(
            $scenario->customer,
            $scenario->country->id,
            $scenario->country->currency_code
        );
        CartItem::create([
            'cart_id' => $cart->id,
            'vendor_listing_id' => $scenario->vendorListingFbp->id,
            'quantity' => 1,
            'unit_price' => (int) $scenario->vendorListingFbp->getRawOriginal('price'),
            'added_at' => now(),
        ]);

        $payload = [
            'address_id' => $scenario->customerAddress->id,
            'country_payment_gateway_id' => $scenario->countryPaymentGateways['cod']->id,
            'idempotency_key' => (string) Str::uuid(),
            'receiver_id' => $receiver->id,
        ];

        $response = $this->postJson(
            "/api/customer/v1/{$scenario->country->site_code}/checkout/place-order",
            $payload
        );

        $response->assertStatus(201);

        $orderNumber = $response->json('data.order.order_number') ?? $response->json('data.order_number');
        $order = Order::where('order_number', $orderNumber)->firstOrFail();

        $this->assertSame('Alternate Receiver', $order->shipping_address_snapshot['recipient_name']);
        $this->assertSame('+971500009999', $order->shipping_address_snapshot['recipient_phone']);
    }
}
