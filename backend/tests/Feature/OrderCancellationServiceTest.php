<?php

namespace Tests\Feature;

use App\Enums\CancelActor;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Enums\WalletOwnerType;
use App\Models\Wallet;
use App\Models\LedgerEntry;
use App\Models\MarketerCampaignConversion;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Refund;
use App\Models\WarrantyPurchase;
use App\Services\OrderCancellationService;
use App\Services\Payments\PaymentGatewayFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\AssertsOrderMoney;
use Tests\Support\FakePaymentGateway;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * enhancement.md P-06: the cancellation engine. Builds real orders through
 * the actual checkout flow (same as PaymentMethodMatrixTest) and then
 * exercises OrderCancellationService against them — tender (wallet/card/
 * cod) x scope (full order/one sub-order/one item) x actor
 * (customer/admin/system).
 */
class OrderCancellationServiceTest extends TestCase
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

    // ── Full order x wallet x customer ──────────────────────────────────

    public function test_full_order_cancel_by_customer_refunds_wallet_releases_stock_and_reverses_ledger(): void
    {
        $scenario = $this->buildScenario();
        $cart = $this->cartFor($scenario);
        $this->addVendorItem($scenario, $cart, $scenario->vendorListingFbp, 1);
        $balanceBefore = $scenario->customerWallet->balance;

        $order = $this->placeOrder($scenario, 'wallet');
        $this->assertSame('captured', $order->payment_status->value);

        app(OrderCancellationService::class)->cancel($order, CancelActor::Customer, 'Changed my mind');

        $order->refresh();
        $this->assertSame('cancelled', $order->status->value);

        $wallet = Wallet::where('owner_type', WalletOwnerType::Customer)->where('owner_id', $scenario->customer->id)->first();
        $this->assertSame($balanceBefore, $wallet->balance, 'wallet must be fully refunded');

        $this->assertStock($scenario->vendorListingFbp, 50, 0);

        $this->assertLedgerBalanced($order->id);
        $reversalGroup = LedgerEntry::where('reference_type', 'order_capture_reversal')->where('reference_id', $order->id)->first();
        $this->assertNotNull($reversalGroup, 'a reversal ledger group must exist for a fully cancelled order');
        $this->assertLedgerBalanced($reversalGroup->transaction_group_id);
    }

    public function test_cancelling_the_same_order_twice_is_idempotent_and_does_not_double_refund(): void
    {
        $scenario = $this->buildScenario();
        $cart = $this->cartFor($scenario);
        $this->addVendorItem($scenario, $cart, $scenario->vendorListingFbp, 1);
        $balanceBefore = $scenario->customerWallet->balance;

        $order = $this->placeOrder($scenario, 'wallet');

        app(OrderCancellationService::class)->cancel($order, CancelActor::Customer, 'first cancel');
        app(OrderCancellationService::class)->cancel($order, CancelActor::Customer, 'second cancel');

        $wallet = Wallet::where('owner_type', WalletOwnerType::Customer)->where('owner_id', $scenario->customer->id)->first();
        $this->assertSame($balanceBefore, $wallet->balance, 'second cancel must not refund the wallet again');

        $this->assertStock($scenario->vendorListingFbp, 50, 0);
    }

    // ── Full order x card x customer ────────────────────────────────────

    public function test_full_order_cancel_card_creates_refund_row_and_calls_gateway(): void
    {
        $scenario = $this->buildScenario();
        $fake = new FakePaymentGateway();
        $fake->scriptDefaultInitiate(FakePaymentGateway::OUTCOME_SUCCESS);
        $fake->scriptDefaultRefund(FakePaymentGateway::OUTCOME_REFUND_SUCCESS);
        PaymentGatewayFactory::fake($fake);

        $cart = $this->cartFor($scenario);
        $this->addVendorItem($scenario, $cart, $scenario->vendorListingFbp, 1);

        $order = $this->placeOrder($scenario, 'stripe');

        // Simulate the gateway capture (webhook/callback would normally do this).
        $tx = PaymentTransaction::where('order_id', $order->id)->where('gateway', 'stripe')->latest()->first();
        $tx->update(['status' => 'succeeded']);
        $order->update(['payment_status' => 'captured']);
        app(\App\Services\LedgerService::class)->postOrderCapture($order, (int) $order->total);

        app(OrderCancellationService::class)->cancel($order, CancelActor::Customer, 'Changed my mind');

        $order->refresh();
        $this->assertSame('cancelled', $order->status->value);
        $this->assertSame('refunded', $order->payment_status->value);

        $refund = Refund::where('order_id', $order->id)->first();
        $this->assertNotNull($refund);
        $this->assertSame('completed', $refund->status->value);
        $this->assertSame((int) $order->total, (int) $refund->amount);
        $this->assertCount(1, $fake->refundCalls);

        $this->assertStock($scenario->vendorListingFbp, 50, 0);
        $this->assertLedgerBalanced($order->id);
    }

    // ── Full order x COD x customer ─────────────────────────────────────

    public function test_full_order_cancel_cod_releases_stock_with_no_refund_needed(): void
    {
        $scenario = $this->buildScenario();
        $cart = $this->cartFor($scenario);
        $this->addVendorItem($scenario, $cart, $scenario->vendorListingFbp, 1);

        $order = $this->placeOrder($scenario, 'cod');
        $this->assertSame('pending', $order->payment_status->value);

        app(OrderCancellationService::class)->cancel($order, CancelActor::Customer, 'no longer needed');

        $order->refresh();
        $this->assertSame('cancelled', $order->status->value);
        $this->assertSame('pending', $order->payment_status->value, 'nothing was captured for COD, so payment_status is untouched');
        $this->assertSame(0, Refund::where('order_id', $order->id)->count());

        $this->assertStock($scenario->vendorListingFbp, 50, 0);
    }

    // ── Partial scope: one sub-order out of two ─────────────────────────

    public function test_cancelling_one_sub_order_only_releases_that_sub_orders_stock_and_prorates_wallet_refund(): void
    {
        $scenario = $this->buildScenario();
        $cart = $this->cartFor($scenario);
        $this->addVendorItem($scenario, $cart, $scenario->vendorListingFbp, 1); // vendor sub-order
        $adminItem = \App\Models\CartItem::create([
            'cart_id' => $cart->id,
            'admin_listing_id' => $scenario->adminListing->id,
            'quantity' => 1,
            'unit_price' => (int) $scenario->adminListing->price,
            'added_at' => now(),
        ]);
        $scenario->customerWallet->update(['balance' => 200000]);
        $balanceBefore = 200000;

        $order = $this->placeOrder($scenario, 'wallet');
        $order->load('subOrders.items');
        $this->assertCount(2, $order->subOrders);

        $vendorSubOrder = $order->subOrders->firstWhere('seller_type', 'vendor');
        $this->assertNotNull($vendorSubOrder);
        $cancelledLineTotal = (int) $vendorSubOrder->items->sum('line_total');

        app(OrderCancellationService::class)->cancel($vendorSubOrder, CancelActor::Customer, 'one vendor changed availability');

        $order->refresh();
        // Not the whole order — the admin-listing sub-order is untouched.
        $this->assertNotSame('cancelled', $order->status->value);

        $vendorSubOrder->refresh();
        $this->assertSame('cancelled', $vendorSubOrder->status->value);

        $platformSubOrder = $order->subOrders()->where('seller_type', 'platform')->first();
        $this->assertNotSame('cancelled', $platformSubOrder->status->value ?? $platformSubOrder->status);

        // Only the vendor listing's stock is released; the admin listing keeps its reservation.
        $this->assertStock($scenario->vendorListingFbp, 50, 0);
        $this->assertStock($scenario->adminListing, 30, 1);

        $wallet = Wallet::where('owner_type', WalletOwnerType::Customer)->where('owner_id', $scenario->customer->id)->first();
        $expectedRefund = (int) round(((int) $order->wallet_amount_used) * ($cancelledLineTotal / max(1, (int) $order->total)));
        $this->assertSame($balanceBefore - ((int) $order->total - $expectedRefund), $wallet->balance);

        // Partial scope: no ledger reversal (documented rule — only a
        // whole-order cancellation reverses the capture ledger group).
        $this->assertSame(0, LedgerEntry::where('reference_type', 'order_capture_reversal')->where('reference_id', $order->id)->count());
    }

    // ── Partial scope: one item within a sub-order ───────────────────────

    public function test_cancelling_one_item_within_a_sub_order_prorates_refund_and_keeps_sub_order_open(): void
    {
        $scenario = $this->buildScenario();
        $cart = $this->cartFor($scenario);
        // Both listings belong to the same vendor -> one vendor sub-order with 2 items.
        $this->addVendorItem($scenario, $cart, $scenario->vendorListingFbp, 1);
        $this->addVendorItem($scenario, $cart, $scenario->vendorListingFbn, 1);

        $order = $this->placeOrder($scenario, 'wallet');
        $order->load('subOrders.items');
        $this->assertCount(1, $order->subOrders);
        $subOrder = $order->subOrders->first();
        $this->assertCount(2, $subOrder->items);

        $itemToCancel = $subOrder->items->firstWhere('vendor_listing_id', $scenario->vendorListingFbp->id);

        app(OrderCancellationService::class)->cancel(collect([$itemToCancel]), CancelActor::Customer, 'only one item unwanted');

        $order->refresh();
        $subOrder->refresh();
        $this->assertNotSame('cancelled', $subOrder->status->value, 'sub-order stays open while the other item is still active');
        $this->assertSame('cancelled', $itemToCancel->fresh()->fulfillment_status->value);

        $remainingItem = $subOrder->items->firstWhere('vendor_listing_id', $scenario->vendorListingFbn->id);
        $this->assertNotSame('cancelled', $remainingItem->fresh()->fulfillment_status->value);

        $this->assertStock($scenario->vendorListingFbp, 50, 0);
        $this->assertStock($scenario->vendorListingFbn, 40, 1);
    }

    // ── Admin force cancel after shipped ────────────────────────────────

    public function test_admin_can_force_cancel_a_shipped_sub_order_but_customer_cannot(): void
    {
        $scenario = $this->buildScenario();
        $cart = $this->cartFor($scenario);
        $this->addVendorItem($scenario, $cart, $scenario->vendorListingFbp, 1);

        $order = $this->placeOrder($scenario, 'cod');
        $order->load('subOrders');
        $subOrder = $order->subOrders->first();
        $subOrder->update(['status' => 'shipped']);

        $this->expectException(\DomainException::class);
        app(OrderCancellationService::class)->cancel($subOrder->fresh(), CancelActor::Customer, 'too late');
    }

    public function test_admin_force_cancel_shipped_sub_order_succeeds(): void
    {
        $scenario = $this->buildScenario();
        $cart = $this->cartFor($scenario);
        $this->addVendorItem($scenario, $cart, $scenario->vendorListingFbp, 1);

        $order = $this->placeOrder($scenario, 'cod');
        $order->load('subOrders');
        $subOrder = $order->subOrders->first();
        $subOrder->update(['status' => 'shipped']);

        app(OrderCancellationService::class)->cancel($subOrder->fresh(), CancelActor::Admin, 'goodwill cancellation', force: true);

        $this->assertSame('cancelled', $subOrder->fresh()->status->value);
    }

    // ── Loyalty, coupon, warranty, marketer conversion ──────────────────

    public function test_full_cancel_restores_loyalty_points_and_releases_coupon_usage(): void
    {
        $scenario = $this->buildScenario();
        $coupon = $scenario->coupons['fixed_amount_platform'];
        $originalTimesUsed = (int) $coupon->times_used;

        $cart = $this->cartFor($scenario);
        $this->addVendorItem($scenario, $cart, $scenario->vendorListingFbp, 1);

        $order = $this->placeOrder($scenario, 'wallet', ['coupon_code' => $coupon->code]);
        $order->refresh();
        $this->assertGreaterThan(0, (int) $order->discount);

        $couponAfterPlace = $coupon->fresh();
        $this->assertSame($originalTimesUsed + 1, (int) $couponAfterPlace->times_used);

        $customer = $scenario->customer->fresh();
        $pointsBefore = (float) $customer->loyalty_points;
        $order->update(['loyalty_points_used' => 100, 'loyalty_discount' => 100]);

        app(OrderCancellationService::class)->cancel($order->fresh(), CancelActor::Customer, 'cancel with coupon+loyalty');

        $this->assertSame($originalTimesUsed, (int) $coupon->fresh()->times_used, 'coupon usage released on full-order cancel');
        $this->assertEquals($pointsBefore + 100, (float) $customer->fresh()->loyalty_points);

        $usage = CouponUsage::where('order_id', $order->id)->first();
        $this->assertSame(CouponUsage::STATUS_RELEASED, $usage->status);
    }

    public function test_cancelling_cancels_warranty_purchases_for_the_cancelled_items(): void
    {
        $scenario = $this->buildScenario();
        $cart = $this->cartFor($scenario);
        $this->addVendorItem($scenario, $cart, $scenario->vendorListingFbp, 1);

        $order = $this->placeOrder($scenario, 'cod');
        $order->load('subOrders.items');
        $item = $order->subOrders->first()->items->first();

        $warrantyPurchase = WarrantyPurchase::create([
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'customer_id' => $scenario->customer->id,
            'warranty_plan_id' => $scenario->warrantyPlanFlat->id,
            'price_paid' => 5000,
            'plan_snapshot' => [],
            'currency' => 'AED',
            'status' => 'pending',
        ]);

        app(OrderCancellationService::class)->cancel($order->fresh(), CancelActor::Customer, 'cancel with warranty');

        $this->assertSame('cancelled', $warrantyPurchase->fresh()->status);
    }

    public function test_cancelling_voids_unpaid_marketer_conversions_for_the_cancelled_items(): void
    {
        $scenario = $this->buildScenario();
        $cart = $this->cartFor($scenario);
        $this->addVendorItem($scenario, $cart, $scenario->vendorListingFbp, 1);

        $order = $this->placeOrder($scenario, 'cod');
        $order->load('subOrders.items');
        $item = $order->subOrders->first()->items->first();

        $conversion = MarketerCampaignConversion::create([
            'campaign_id' => $scenario->marketerCampaign->id,
            'invitation_id' => $scenario->marketerCampaignInvitation->id,
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'commission_amount' => 500,
            'currency' => 'AED',
            'commissioned' => false,
        ]);

        app(OrderCancellationService::class)->cancel($order->fresh(), CancelActor::Customer, 'cancel with marketer conversion');

        $this->assertFalse((bool) $conversion->fresh()->commissioned);
    }

    // ── System actor (RTO) ───────────────────────────────────────────────

    public function test_system_actor_can_cancel_regardless_of_status(): void
    {
        $scenario = $this->buildScenario();
        $cart = $this->cartFor($scenario);
        $this->addVendorItem($scenario, $cart, $scenario->vendorListingFbp, 1);

        $order = $this->placeOrder($scenario, 'cod');
        $order->load('subOrders');
        $subOrder = $order->subOrders->first();
        $subOrder->update(['status' => 'out_for_delivery']);

        app(OrderCancellationService::class)->cancel($subOrder->fresh(), CancelActor::System, 'RTO: customer refused COD delivery');

        $this->assertSame('cancelled', $subOrder->fresh()->status->value);
    }
}
