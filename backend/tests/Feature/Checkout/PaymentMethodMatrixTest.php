<?php

namespace Tests\Feature\Checkout;

use App\Models\CartItem;
use App\Models\CustomerWallet;
use App\Models\IdempotencyKey;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Services\Payments\PaymentGatewayFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\Support\AssertsOrderMoney;
use Tests\Support\FakePaymentGateway;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * enhancement.md P-05 (Phase B): the payment-method matrix — wallet
 * (full/partial), COD, card gateway, bank transfer — end to end through
 * the real place-order/callback/webhook endpoints.
 */
class PaymentMethodMatrixTest extends TestCase
{
    use RefreshDatabase;
    use AssertsOrderMoney;

    protected function tearDown(): void
    {
        PaymentGatewayFactory::fake(null);
        parent::tearDown();
    }

    private function buildScenario(): MarketplaceScenario
    {
        $scenario = MarketplaceScenario::make()->build();
        $scenario->country->update(['site_code' => 'ae-'.Str::lower(Str::random(6))]);

        return $scenario;
    }

    private function addVendorItem(MarketplaceScenario $scenario, $cart, $listing, int $quantity = 1): CartItem
    {
        return CartItem::create([
            'cart_id' => $cart->id,
            'vendor_listing_id' => $listing->id,
            'quantity' => $quantity,
            'unit_price' => (int) $listing->getRawOriginal('price'),
            'added_at' => now(),
        ]);
    }

    private function cartFor(MarketplaceScenario $scenario)
    {
        $this->actingAs($scenario->customer, 'customer');

        return app(\App\Services\Customer\CartService::class)
            ->getOrCreateCart($scenario->customer, $scenario->country->id, $scenario->country->currency_code);
    }

    private function placeOrder(MarketplaceScenario $scenario, string $gatewayCode, array $extra = []): \Illuminate\Testing\TestResponse
    {
        $payload = array_merge([
            'address_id' => $scenario->customerAddress->id,
            'country_payment_gateway_id' => $scenario->countryPaymentGateways[$gatewayCode]->id,
            'idempotency_key' => (string) Str::uuid(),
        ], $extra);

        return $this->postJson("/api/customer/v1/{$scenario->country->site_code}/checkout/place-order", $payload);
    }

    // ── Wallet: full ─────────────────────────────────────────────────────

    public function test_wallet_full_payment_captures_in_transaction_and_debits_wallet_once(): void
    {
        $scenario = $this->buildScenario();
        $cart = $this->cartFor($scenario);
        $this->addVendorItem($scenario, $cart, $scenario->vendorListingFbp, 1);
        $balanceBefore = $scenario->customerWallet->balance;

        $response = $this->placeOrder($scenario, 'wallet');
        $response->assertStatus(201);

        $orderNumber = $response->json('data.order.order_number') ?? $response->json('data.order_number');
        $order = Order::where('order_number', $orderNumber)->firstOrFail();

        $this->assertSame('captured', $order->payment_status->value);
        $this->assertSame('wallet', $order->payment_method);

        $wallet = CustomerWallet::where('customer_id', $scenario->customer->id)->first();
        $this->assertSame($balanceBefore - $order->total, $wallet->balance);

        // enhancement.md P-05 task 3: a wallet-only order must still create
        // a payment_transactions row so idempotency can be detected.
        $tx = PaymentTransaction::where('order_id', $order->id)->where('gateway', 'wallet')->first();
        $this->assertNotNull($tx);
        $this->assertSame('succeeded', $tx->status->value);
    }

    // ── Wallet (partial) + card ──────────────────────────────────────────

    public function test_partial_wallet_plus_card_charges_gateway_only_for_remainder(): void
    {
        $scenario = $this->buildScenario();
        $fake = new FakePaymentGateway();
        $fake->scriptDefaultInitiate(FakePaymentGateway::OUTCOME_SUCCESS);
        PaymentGatewayFactory::fake($fake);

        $cart = $this->cartFor($scenario);
        $this->addVendorItem($scenario, $cart, $scenario->vendorListingFbp, 1);
        $balanceBefore = $scenario->customerWallet->balance;

        $walletAmount = 1000; // partial

        $response = $this->placeOrder($scenario, 'stripe', ['wallet_amount_used' => $walletAmount]);
        $response->assertStatus(201);

        $orderNumber = $response->json('data.order.order_number') ?? $response->json('data.order_number');
        $order = Order::where('order_number', $orderNumber)->firstOrFail();

        $wallet = CustomerWallet::where('customer_id', $scenario->customer->id)->first();
        $this->assertSame($balanceBefore - $walletAmount, $wallet->balance, 'wallet must be debited exactly once for the partial amount');

        // The gateway must have been asked to charge total - wallet, never the full total (double charge bug).
        $this->assertCount(1, $fake->initiateCalls);
        $this->assertSame($order->total - $walletAmount, $fake->initiateCalls[0]['data']->amountCents);

        $this->assertSame('card', $order->payment_method);
        $this->assertSame('stripe', $order->payment_gateway_code);
    }

    public function test_partial_wallet_plus_card_decline_refunds_wallet_and_releases_stock(): void
    {
        $scenario = $this->buildScenario();
        $fake = new FakePaymentGateway();
        $fake->scriptInitiate(FakePaymentGateway::OUTCOME_DECLINE);
        PaymentGatewayFactory::fake($fake);

        $cart = $this->cartFor($scenario);
        $this->addVendorItem($scenario, $cart, $scenario->vendorListingFbp, 1);
        $balanceBefore = $scenario->customerWallet->balance;

        $response = $this->placeOrder($scenario, 'stripe', ['wallet_amount_used' => 1000]);
        $response->assertStatus(201);

        $orderNumber = $response->json('data.order.order_number') ?? $response->json('data.order_number');
        $order = Order::where('order_number', $orderNumber)->firstOrFail();

        $order->refresh();
        $this->assertSame('failed', $order->payment_status->value);
        $this->assertSame('cancelled', $order->status->value ?? $order->status);

        $wallet = CustomerWallet::where('customer_id', $scenario->customer->id)->first();
        $this->assertSame($balanceBefore, $wallet->balance, 'wallet debit must be refunded on decline');

        $inventory = $scenario->vendorListingFbp->warehouseInventories()->first();
        $this->assertSame(0, $inventory->quantity_reserved);
    }

    public function test_card_payment_exception_rolls_back_and_does_not_clear_cart(): void
    {
        $scenario = $this->buildScenario();
        $fake = new FakePaymentGateway();
        $fake->scriptInitiate(FakePaymentGateway::OUTCOME_EXCEPTION);
        PaymentGatewayFactory::fake($fake);

        $cart = $this->cartFor($scenario);
        $this->addVendorItem($scenario, $cart, $scenario->vendorListingFbp, 1);

        $response = $this->placeOrder($scenario, 'stripe');
        $response->assertStatus(201);

        $orderNumber = $response->json('data.order.order_number') ?? $response->json('data.order_number');
        $order = Order::where('order_number', $orderNumber)->firstOrFail();
        $this->assertSame('failed', $order->payment_status->value);

        // Cart must NOT have been cleared on failure (enhancement.md P-05 task 4).
        $cart->refresh();
        $this->assertGreaterThan(0, $cart->items()->count());
    }

    // ── COD ──────────────────────────────────────────────────────────────

    public function test_cod_order_stays_pending_until_delivery_collection(): void
    {
        $scenario = $this->buildScenario();
        $cart = $this->cartFor($scenario);
        $this->addVendorItem($scenario, $cart, $scenario->vendorListingFbp, 1);

        $response = $this->placeOrder($scenario, 'cod');
        $response->assertStatus(201);

        $orderNumber = $response->json('data.order.order_number') ?? $response->json('data.order_number');
        $order = Order::where('order_number', $orderNumber)->firstOrFail();

        $this->assertSame('pending', $order->payment_status->value);
        $this->assertSame('cod', $order->payment_method);

        // Cart clears for a legitimately-pending method (COD).
        $cart->refresh();
        $this->assertSame(0, $cart->items()->count());
    }

    public function test_cod_rejects_partial_wallet_top_up(): void
    {
        $scenario = $this->buildScenario();
        $cart = $this->cartFor($scenario);
        $this->addVendorItem($scenario, $cart, $scenario->vendorListingFbp, 1);

        $response = $this->placeOrder($scenario, 'cod', ['wallet_amount_used' => 100]);
        $response->assertStatus(422);
    }

    // ── Bank transfer ────────────────────────────────────────────────────

    public function test_bank_transfer_order_pending_until_admin_confirms(): void
    {
        $scenario = $this->buildScenario();
        $fake = (new FakePaymentGateway())->withCode('bank_transfer');
        $fake->scriptDefaultInitiate(FakePaymentGateway::OUTCOME_SUCCESS);
        PaymentGatewayFactory::fake($fake);

        $cart = $this->cartFor($scenario);
        $this->addVendorItem($scenario, $cart, $scenario->vendorListingFbp, 1);

        $response = $this->placeOrder($scenario, 'bank_transfer');
        $response->assertStatus(201);

        $orderNumber = $response->json('data.order.order_number') ?? $response->json('data.order_number');
        $order = Order::where('order_number', $orderNumber)->firstOrFail();
        $this->assertSame('pending', $order->payment_status->value);

        $transaction = PaymentTransaction::where('order_id', $order->id)->where('gateway', 'bank_transfer')->latest()->first();
        $this->assertNotNull($transaction);

        $admin = \App\Models\Admin::factory()->create();
        \Spatie\Permission\Models\Permission::findOrCreate('transactions.view', 'admin');
        \Spatie\Permission\Models\Permission::findOrCreate('vendors.assigned_only', 'admin');
        $admin->givePermissionTo('transactions.view');
        $this->actingAs($admin, 'admin');

        $confirm = $this->postJson(route('admin.transactions.confirm-bank-transfer', $transaction->id));
        $confirm->assertOk();

        $order->refresh();
        $this->assertSame('captured', $order->payment_status->value);
    }

    // ── Callback security ────────────────────────────────────────────────

    public function test_anonymous_cancel_without_signature_or_failed_gateway_state_is_forbidden(): void
    {
        $scenario = $this->buildScenario();
        $fake = new FakePaymentGateway();
        $fake->scriptDefaultInitiate(FakePaymentGateway::OUTCOME_SUCCESS);
        PaymentGatewayFactory::fake($fake);

        $cart = $this->cartFor($scenario);
        $this->addVendorItem($scenario, $cart, $scenario->vendorListingFbp, 1);

        $response = $this->placeOrder($scenario, 'stripe');
        $response->assertStatus(201);
        $orderNumber = $response->json('data.order.order_number') ?? $response->json('data.order_number');

        // No auth needed for this route, but no valid signature either.
        $cancel = $this->getJson("/api/customer/v1/{$scenario->country->site_code}/checkout/payment/cancel/{$orderNumber}");
        $cancel->assertStatus(403);
    }

    public function test_signed_cancel_url_is_accepted(): void
    {
        $scenario = $this->buildScenario();
        $fake = new FakePaymentGateway();
        $fake->scriptDefaultInitiate(FakePaymentGateway::OUTCOME_SUCCESS);
        PaymentGatewayFactory::fake($fake);

        $cart = $this->cartFor($scenario);
        $this->addVendorItem($scenario, $cart, $scenario->vendorListingFbp, 1);

        $response = $this->placeOrder($scenario, 'stripe');
        $response->assertStatus(201);
        $orderNumber = $response->json('data.order.order_number') ?? $response->json('data.order_number');

        $signedUrl = URL::signedRoute('checkout.cancel', [
            'country' => $scenario->country->site_code,
            'orderNumber' => $orderNumber,
        ]);

        $path = str_replace(url(''), '', $signedUrl);
        $cancel = $this->getJson($path);
        $cancel->assertOk();

        $order = Order::where('order_number', $orderNumber)->firstOrFail();
        $this->assertSame('failed', $order->payment_status->value);
    }

    // ── Webhook-first capture / idempotency ─────────────────────────────

    public function test_webhook_arriving_before_callback_captures_the_order(): void
    {
        $scenario = $this->buildScenario();
        $fake = new FakePaymentGateway();
        $fake->scriptDefaultInitiate(FakePaymentGateway::OUTCOME_SUCCESS);
        PaymentGatewayFactory::fake($fake);

        $cart = $this->cartFor($scenario);
        $this->addVendorItem($scenario, $cart, $scenario->vendorListingFbp, 1);

        $response = $this->placeOrder($scenario, 'stripe');
        $response->assertStatus(201);
        $orderNumber = $response->json('data.order.order_number') ?? $response->json('data.order_number');
        $order = Order::where('order_number', $orderNumber)->firstOrFail();

        $webhook = $this->postJson('/webhooks/payment/stripe', [
            'order_reference' => $orderNumber,
            'type' => 'payment.succeeded',
        ]);
        $webhook->assertOk();

        $order->refresh();
        $this->assertSame('captured', $order->payment_status->value);

        $ledgerCountAfterFirst = LedgerEntry::where('reference_type', 'order_capture')->where('reference_id', $order->id)->count();
        $this->assertGreaterThan(0, $ledgerCountAfterFirst);

        // Duplicate webhook must be a no-op (idempotent).
        $webhook2 = $this->postJson('/webhooks/payment/stripe', [
            'order_reference' => $orderNumber,
            'type' => 'payment.succeeded',
        ]);
        $webhook2->assertOk();

        $ledgerCountAfterSecond = LedgerEntry::where('reference_type', 'order_capture')->where('reference_id', $order->id)->count();
        $this->assertSame($ledgerCountAfterFirst, $ledgerCountAfterSecond, 'duplicate webhook must not double-post the ledger');
    }

    // ── Idempotent place-order retry ─────────────────────────────────────

    public function test_duplicate_place_order_request_returns_cached_result_not_a_second_order(): void
    {
        $scenario = $this->buildScenario();
        $cart = $this->cartFor($scenario);
        $this->addVendorItem($scenario, $cart, $scenario->vendorListingFbp, 1);

        $key = (string) Str::uuid();
        $payload = [
            'address_id' => $scenario->customerAddress->id,
            'country_payment_gateway_id' => $scenario->countryPaymentGateways['cod']->id,
            'idempotency_key' => $key,
        ];

        $first = $this->postJson("/api/customer/v1/{$scenario->country->site_code}/checkout/place-order", $payload);
        $first->assertStatus(201);
        $firstOrderNumber = $first->json('data.order.order_number') ?? $first->json('data.order_number');

        $second = $this->postJson("/api/customer/v1/{$scenario->country->site_code}/checkout/place-order", $payload);
        $second->assertStatus(201);
        $secondOrderNumber = $second->json('data.order.order_number') ?? $second->json('data.order_number');

        $this->assertSame($firstOrderNumber, $secondOrderNumber);
        $this->assertSame(1, Order::where('order_number', $firstOrderNumber)->count());

        $idem = IdempotencyKey::where('key', $key)->first();
        $this->assertNotNull($idem);
        $this->assertSame(201, $idem->response_status);
    }
}
