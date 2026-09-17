<?php

namespace Tests\Feature;

use App\DTOs\Refund\RefundScope;
use App\Models\CartItem;
use App\Enums\WalletOwnerType;
use App\Models\Wallet;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Refund;
use App\Services\Payments\PaymentGatewayFactory;
use App\Services\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\AssertsOrderMoney;
use Tests\Support\FakePaymentGateway;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * enhancement.md P-07: RefundService — no double refund, COD refunds go to
 * the wallet, returning N of M units refunds exactly that share, and a
 * store-credit return credits the wallet exactly once.
 */
class RefundServiceTest extends TestCase
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

    private function cartFor(MarketplaceScenario $scenario)
    {
        $this->actingAs($scenario->customer, 'customer');

        return app(\App\Services\Customer\CartService::class)
            ->getOrCreateCart($scenario->customer, $scenario->country->id, $scenario->country->currency_code);
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

    private function placeOrder(MarketplaceScenario $scenario, string $gatewayCode, array $extra = []): Order
    {
        $payload = array_merge([
            'address_id' => $scenario->customerAddress->id,
            'country_payment_gateway_id' => $scenario->countryPaymentGateways[$gatewayCode]->id,
            'idempotency_key' => (string) Str::uuid(),
        ], $extra);

        $response = $this->postJson("/api/customer/v1/{$scenario->country->site_code}/checkout/place-order", $payload);
        $response->assertStatus(201);

        $orderNumber = $response->json('data.order.order_number') ?? $response->json('data.order_number');

        return Order::where('order_number', $orderNumber)->firstOrFail();
    }

    private function captureCardOrder(Order $order): void
    {
        $tx = PaymentTransaction::where('order_id', $order->id)->where('gateway', 'stripe')->latest()->first();
        $tx->update(['status' => 'succeeded']);
        $order->update(['payment_status' => 'captured']);
        app(\App\Services\LedgerService::class)->postOrderCapture($order, (int) $order->total);
    }

    // ── Card refund: exactly to the gateway, nothing to the wallet ─────────

    public function test_card_refund_of_100_moves_exactly_100_to_the_card_and_0_to_the_wallet(): void
    {
        $scenario = $this->buildScenario();
        $fake = new FakePaymentGateway();
        $fake->scriptDefaultInitiate(FakePaymentGateway::OUTCOME_SUCCESS);
        $fake->scriptDefaultRefund(FakePaymentGateway::OUTCOME_REFUND_SUCCESS);
        PaymentGatewayFactory::fake($fake);

        $cart = $this->cartFor($scenario);
        $this->addVendorItem($scenario, $cart, $scenario->vendorListingFbp, 1);
        $order = $this->placeOrder($scenario, 'stripe');
        $this->captureCardOrder($order);

        $walletBefore = Wallet::where('owner_type', WalletOwnerType::Customer)->where('owner_id', $scenario->customer->id)->first()?->balance ?? 0;

        $order->load('subOrders.items');

        $refund = app(RefundService::class)->refund(
            order: $order,
            scope: RefundScope::amount(100),
            reason: 'customer_request',
            liability: 'platform',
            destination: 'original',
        );

        $refund->refresh();
        $this->assertSame('completed', $refund->status->value);
        $this->assertSame(100, (int) $refund->amount);
        $this->assertSame(0, (int) $refund->gateway_fee_deducted, 'platform-fault refund deducts nothing');
        $this->assertSame(100, (int) $refund->net_refund, 'exactly 100 moves to the card');
        $this->assertCount(1, $fake->refundCalls);
        $this->assertSame(100, $fake->refundCalls[0]['amountCents']);

        $walletAfter = Wallet::where('owner_type', WalletOwnerType::Customer)->where('owner_id', $scenario->customer->id)->first()?->balance ?? 0;
        $this->assertSame($walletBefore, $walletAfter, 'nothing must move to the wallet for a card refund');
    }

    // ── COD refund: to the wallet, never through the gateway ───────────────

    public function test_cod_refund_credits_the_wallet_not_the_gateway(): void
    {
        $scenario = $this->buildScenario();
        $cart = $this->cartFor($scenario);
        $this->addVendorItem($scenario, $cart, $scenario->vendorListingFbp, 1);
        $order = $this->placeOrder($scenario, 'cod');
        $order->load('subOrders.items');
        $subOrder = $order->subOrders->first();

        $walletBefore = Wallet::where('owner_type', WalletOwnerType::Customer)->where('owner_id', $scenario->customer->id)->first()?->balance ?? 0;
        $item = $subOrder->items->first();

        $refund = app(RefundService::class)->refund(
            order: $order,
            scope: RefundScope::items($subOrder->id, [$item->id => (int) $item->quantity]),
            reason: 'customer_request',
            liability: 'customer',
            destination: 'original',
        );

        $refund->refresh();
        $this->assertSame('completed', $refund->status->value);
        $this->assertSame((int) $item->line_total + (int) $subOrder->shipping, (int) $refund->amount);

        $wallet = Wallet::where('owner_type', WalletOwnerType::Customer)->where('owner_id', $scenario->customer->id)->first();
        $this->assertSame($walletBefore + (int) $refund->net_refund, $wallet->balance, 'COD refund lands in the wallet');
        $this->assertNull($refund->original_transaction_id, 'COD refund never touches a gateway transaction');
    }

    // ── Return 1 of 3 units: exactly that unit's share, from persisted values ─

    public function test_returning_one_of_three_units_refunds_exactly_that_units_share(): void
    {
        $scenario = $this->buildScenario();
        $cart = $this->cartFor($scenario);
        $this->addVendorItem($scenario, $cart, $scenario->vendorListingFbp, 3);
        $order = $this->placeOrder($scenario, 'cod');
        $order->load('subOrders.items');
        $subOrder = $order->subOrders->first();
        $item = $subOrder->items->first();
        $this->assertSame(3, (int) $item->quantity);

        $expectedShare = (int) round(((int) $item->line_total) * (1 / 3));

        $refund = app(RefundService::class)->refund(
            order: $order,
            scope: RefundScope::items($subOrder->id, [$item->id => 1]),
            reason: 'changed_mind',
            liability: 'customer',
            destination: 'original',
        );

        $refund->refresh();
        $this->assertSame($expectedShare, (int) $refund->amount, 'only 1/3 of the line, no shipping (not every unit returned)');
        $this->assertSame('partial', $refund->refund_type->value);
        // changed_mind isn't a Refund.reason enum value -> mapped to customer_request.
        $this->assertSame('customer_request', $refund->reason->value);
        $this->assertSame($refund->reason_notes, 'Original reason: changed_mind');
    }

    public function test_returning_all_units_of_a_sub_order_includes_shipping(): void
    {
        $scenario = $this->buildScenario();
        $cart = $this->cartFor($scenario);
        $this->addVendorItem($scenario, $cart, $scenario->vendorListingFbp, 2);
        $order = $this->placeOrder($scenario, 'cod');
        $order->load('subOrders.items');
        $subOrder = $order->subOrders->first();
        $item = $subOrder->items->first();

        $refund = app(RefundService::class)->refund(
            order: $order,
            scope: RefundScope::items($subOrder->id, [$item->id => 2]),
            reason: 'defective',
            liability: 'seller',
            destination: 'original',
        );

        $refund->refresh();
        $expected = (int) $item->line_total + (int) $subOrder->shipping;
        $this->assertSame($expected, (int) $refund->amount);
        $this->assertSame('full', $refund->refund_type->value);
        $this->assertTrue((bool) $refund->vendor_charged_back, 'seller liability charges the vendor back');
        $this->assertSame(0, (int) $refund->gateway_fee_deducted, 'seller-fault refund deducts nothing');
    }

    // ── Store-credit return: wallet exactly once ────────────────────────────

    public function test_store_credit_return_credits_the_wallet_exactly_once(): void
    {
        $scenario = $this->buildScenario();
        $fake = new FakePaymentGateway();
        $fake->scriptDefaultInitiate(FakePaymentGateway::OUTCOME_SUCCESS);
        PaymentGatewayFactory::fake($fake);

        $cart = $this->cartFor($scenario);
        $this->addVendorItem($scenario, $cart, $scenario->vendorListingFbp, 1);
        $order = $this->placeOrder($scenario, 'stripe');
        $this->captureCardOrder($order);
        $order->load('subOrders.items');
        $subOrder = $order->subOrders->first();
        $item = $subOrder->items->first();

        $walletBefore = Wallet::where('owner_type', WalletOwnerType::Customer)->where('owner_id', $scenario->customer->id)->first()?->balance ?? 0;

        $refund = app(RefundService::class)->refund(
            order: $order,
            scope: RefundScope::items($subOrder->id, [$item->id => (int) $item->quantity]),
            reason: 'quality_issue',
            liability: 'platform',
            destination: 'wallet',
        );

        $refund->refresh();
        $this->assertSame('completed', $refund->status->value);
        $this->assertSame(0, count($fake->refundCalls), 'store credit never touches the gateway');

        $wallet = Wallet::where('owner_type', WalletOwnerType::Customer)->where('owner_id', $scenario->customer->id)->first();
        $this->assertSame($walletBefore + (int) $refund->net_refund, $wallet->balance, 'wallet credited exactly once');
    }

    // ── RefundProcessingJob: no double credit for a legacy admin refund ────

    public function test_refund_processing_job_does_not_double_credit_the_wallet(): void
    {
        $scenario = $this->buildScenario();
        $cart = $this->cartFor($scenario);
        $this->addVendorItem($scenario, $cart, $scenario->vendorListingFbp, 1);
        $order = $this->placeOrder($scenario, 'cod');

        $walletBefore = Wallet::where('owner_type', WalletOwnerType::Customer)->where('owner_id', $scenario->customer->id)->first()?->balance ?? 0;

        // A legacy refund row created outside RefundService (as
        // OrderInterventionService::processRefund does) — no liability, no
        // pre-set destination, status 'approved'/'processing'.
        $refund = Refund::create([
            'order_id' => $order->id,
            'sub_order_id' => null,
            'amount' => 500,
            'currency' => $order->currency,
            'reason' => 'customer_request',
            'refund_type' => 'partial',
            'initiated_by_customer_id' => null,
            'initiated_by_type' => 'admin',
            'initiated_by_id' => (string) Str::uuid(),
            'vendor_charged_back' => false,
            'status' => 'processing',
        ]);
        $refund->refresh();

        (new \App\Jobs\RefundProcessingJob($refund))->handle(app(RefundService::class));

        $refund->refresh();
        $this->assertSame('completed', $refund->status->value);

        $wallet = Wallet::where('owner_type', WalletOwnerType::Customer)->where('owner_id', $scenario->customer->id)->first();
        $this->assertSame($walletBefore + (int) $refund->net_refund, $wallet->balance, 'credited exactly once, not twice');
    }
}
