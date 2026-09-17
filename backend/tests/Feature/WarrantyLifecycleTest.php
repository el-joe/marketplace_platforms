<?php

namespace Tests\Feature;

use App\Enums\CancelActor;
use App\Jobs\ExpireWarrantyPurchasesJob;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\SubOrder;
use App\Models\WarrantyClaim;
use App\Models\WarrantyPurchase;
use App\Services\OrderStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * enhancement.md P-09: warranty lifecycle — purchase at checkout,
 * activation on delivery (D3 coverage-start rule), expiry, cancellation,
 * and claims (brand vs platform window, resolution actions).
 */
class WarrantyLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Notification::fake();
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

    private function deliver(SubOrder $subOrder): SubOrder
    {
        $machine = app(OrderStateMachine::class);
        foreach (['confirmed', 'processing', 'packed', 'shipped'] as $step) {
            $machine->transition($subOrder, $step, CancelActor::System);
        }
        $machine->transition($subOrder, 'delivered', CancelActor::System);

        return $subOrder->refresh();
    }

    // ── Activation ──────────────────────────────────────────────────────────

    public function test_delivered_vendor_item_with_plan_activates_after_brand_warranty_window(): void
    {
        $scenario = $this->buildScenario();
        $cart = $this->cartFor($scenario);
        CartItem::create([
            'cart_id' => $cart->id,
            'vendor_listing_id' => $scenario->vendorListingFbp->id,
            'quantity' => 1,
            'unit_price' => (int) $scenario->vendorListingFbp->getRawOriginal('price'),
            'warranty_plan_id' => $scenario->warrantyPlanFlat->id,
            'added_at' => now(),
        ]);

        $order = $this->placeOrder($scenario, 'wallet');
        $subOrder = $order->subOrders()->where('seller_type', 'vendor')->first();
        $item = $subOrder->items()->first();

        $purchase = WarrantyPurchase::where('order_item_id', $item->id)->first();
        $this->assertNotNull($purchase, 'checkout must create a pending warranty_purchases row from cart_items.warranty_plan_id');
        $this->assertSame('pending', $purchase->status);

        $subOrder = $this->deliver($subOrder);

        $purchase->refresh();
        $this->assertSame('active', $purchase->status);
        $this->assertNotNull($purchase->coverage_starts_at);

        // Vendor has warranty_months = 12 (MarketplaceScenario), so the
        // platform coverage starts 12 months after delivery, not at
        // delivery, and runs for the plan's own duration (12 months).
        $expectedStart = $subOrder->delivered_at->copy()->addMonths(12)->toDateString();
        $expectedEnd = $subOrder->delivered_at->copy()->addMonths(12)->addMonths(12)->toDateString();

        $this->assertSame($expectedStart, $purchase->coverage_starts_at->toDateString());
        $this->assertSame($expectedEnd, $purchase->coverage_ends_at->toDateString());
    }

    public function test_delivered_admin_listing_item_with_plan_activates_at_delivery_with_no_brand_warranty(): void
    {
        $scenario = $this->buildScenario();
        $cart = $this->cartFor($scenario);
        CartItem::create([
            'cart_id' => $cart->id,
            'admin_listing_id' => $scenario->adminListing->id,
            'quantity' => 1,
            'unit_price' => (int) $scenario->adminListing->getRawOriginal('price'),
            'warranty_plan_id' => $scenario->warrantyPlanFlat->id,
            'added_at' => now(),
        ]);

        $order = $this->placeOrder($scenario, 'cod');
        $subOrder = $order->subOrders()->where('seller_type', 'platform')->first();
        $item = $subOrder->items()->first();

        $purchase = WarrantyPurchase::where('order_item_id', $item->id)->first();
        $this->assertNotNull($purchase, 'warranty selection must work for admin-listing items too (P-02 CartLineSource)');

        $subOrder = $this->deliver($subOrder);
        $purchase->refresh();

        $this->assertSame('active', $purchase->status);
        // No vendor => no brand warranty => coverage starts at delivery.
        $this->assertSame($subOrder->delivered_at->toDateString(), $purchase->coverage_starts_at->toDateString());
        $this->assertSame(
            $subOrder->delivered_at->copy()->addMonths(12)->toDateString(),
            $purchase->coverage_ends_at->toDateString(),
        );
    }

    // ── Expiry job ───────────────────────────────────────────────────────────

    public function test_expire_warranty_purchases_job_transitions_active_to_expired(): void
    {
        $scenario = $this->buildScenario();
        $cart = $this->cartFor($scenario);
        CartItem::create([
            'cart_id' => $cart->id,
            'vendor_listing_id' => $scenario->vendorListingFbp->id,
            'quantity' => 1,
            'unit_price' => (int) $scenario->vendorListingFbp->getRawOriginal('price'),
            'warranty_plan_id' => $scenario->warrantyPlanFlat->id,
            'added_at' => now(),
        ]);

        $order = $this->placeOrder($scenario, 'wallet');
        $subOrder = $order->subOrders()->where('seller_type', 'vendor')->first();
        $this->deliver($subOrder);
        $item = $subOrder->items()->first();
        $purchase = WarrantyPurchase::where('order_item_id', $item->id)->firstOrFail();

        // Backdate it as if coverage already ended, and add a second,
        // still-covered purchase to prove the job is selective.
        $purchase->update(['coverage_ends_at' => now()->subDay()->toDateString()]);

        (new ExpireWarrantyPurchasesJob())->handle();
        $this->assertSame('expired', $purchase->refresh()->status);

        $purchase->update(['status' => 'active', 'coverage_ends_at' => now()->addMonths(6)->toDateString()]);
        (new ExpireWarrantyPurchasesJob())->handle();
        $this->assertSame('active', $purchase->refresh()->status);
    }

    // ── Cancellation ─────────────────────────────────────────────────────────

    public function test_cancelled_items_warranty_purchase_is_cancelled(): void
    {
        $scenario = $this->buildScenario();
        $cart = $this->cartFor($scenario);
        CartItem::create([
            'cart_id' => $cart->id,
            'vendor_listing_id' => $scenario->vendorListingFbp->id,
            'quantity' => 1,
            'unit_price' => (int) $scenario->vendorListingFbp->getRawOriginal('price'),
            'warranty_plan_id' => $scenario->warrantyPlanFlat->id,
            'added_at' => now(),
        ]);

        $order = $this->placeOrder($scenario, 'wallet');
        $subOrder = $order->subOrders()->where('seller_type', 'vendor')->first();
        $item = $subOrder->items()->first();
        $purchase = WarrantyPurchase::where('order_item_id', $item->id)->first();
        $this->assertSame('pending', $purchase->status);

        app(\App\Services\OrderCancellationService::class)->cancel(
            $order,
            \App\Enums\CancelActor::Customer,
            'customer_request',
        );

        $this->assertSame('cancelled', $purchase->refresh()->status);
    }

    // ── Claims ───────────────────────────────────────────────────────────────

    public function test_claim_without_platform_warranty_but_inside_brand_window_succeeds_as_brand(): void
    {
        $scenario = $this->buildScenario();
        $cart = $this->cartFor($scenario);
        // No warranty plan selected — only the vendor's brand warranty
        // (warranty_months = 12 per MarketplaceScenario) applies.
        CartItem::create([
            'cart_id' => $cart->id,
            'vendor_listing_id' => $scenario->vendorListingFbp->id,
            'quantity' => 1,
            'unit_price' => (int) $scenario->vendorListingFbp->getRawOriginal('price'),
            'added_at' => now(),
        ]);

        $order = $this->placeOrder($scenario, 'wallet');
        $subOrder = $order->subOrders()->where('seller_type', 'vendor')->first();
        $this->deliver($subOrder);
        $item = $subOrder->items()->first();

        $this->assertNull(WarrantyPurchase::where('order_item_id', $item->id)->first());

        $this->actingAs($scenario->customer, 'customer');
        $response = $this->postJson(
            "/api/customer/v1/{$scenario->country->site_code}/warranty/claims",
            [
                'order_item_id' => $item->id,
                'issue_type' => 'defective',
                'issue_description' => 'The product stopped working after a week.',
            ],
        );

        $response->assertStatus(201);
        $claim = WarrantyClaim::first();
        $this->assertNotNull($claim);
        $this->assertSame('brand', $claim->claim_type);
        $this->assertFalse($claim->covered_by_platform_warranty);
        $this->assertNull($claim->warranty_purchase_id);
    }

    public function test_claim_outside_every_warranty_window_returns_422(): void
    {
        $scenario = $this->buildScenario();
        $cart = $this->cartFor($scenario);
        CartItem::create([
            'cart_id' => $cart->id,
            'vendor_listing_id' => $scenario->vendorListingFbp->id,
            'quantity' => 1,
            'unit_price' => (int) $scenario->vendorListingFbp->getRawOriginal('price'),
            'added_at' => now(),
        ]);

        $order = $this->placeOrder($scenario, 'wallet');
        $subOrder = $order->subOrders()->where('seller_type', 'vendor')->first();
        $this->deliver($subOrder);
        // Push delivery far enough into the past that both the brand
        // window (12 months) and any platform warranty have lapsed.
        $subOrder->update(['delivered_at' => now()->subYears(3)]);
        $item = $subOrder->items()->first();

        $this->actingAs($scenario->customer, 'customer');
        $response = $this->postJson(
            "/api/customer/v1/{$scenario->country->site_code}/warranty/claims",
            [
                'order_item_id' => $item->id,
                'issue_type' => 'defective',
                'issue_description' => 'The product stopped working after a week.',
            ],
        );

        $response->assertStatus(422);
    }

    public function test_replace_resolution_creates_replacement_sub_order(): void
    {
        $scenario = $this->buildScenario();
        $cart = $this->cartFor($scenario);
        CartItem::create([
            'cart_id' => $cart->id,
            'vendor_listing_id' => $scenario->vendorListingFbp->id,
            'quantity' => 1,
            'unit_price' => (int) $scenario->vendorListingFbp->getRawOriginal('price'),
            'warranty_plan_id' => $scenario->warrantyPlanFlat->id,
            'added_at' => now(),
        ]);

        $order = $this->placeOrder($scenario, 'wallet');
        $subOrder = $order->subOrders()->where('seller_type', 'vendor')->first();
        $this->deliver($subOrder);
        $item = $subOrder->items()->first();

        $claim = WarrantyClaim::create([
            'claim_number' => 'WC-TEST01',
            'customer_id' => $scenario->customer->id,
            'order_item_id' => $item->id,
            'product_id' => $scenario->product->id,
            'vendor_id' => $scenario->vendor->id,
            'listing_type' => WarrantyClaim::LISTING_TYPE_VENDOR,
            'claim_type' => WarrantyClaim::CLAIM_TYPE_PLATFORM,
            'issue_type' => 'defective',
            'issue_description' => 'Broken on arrival, needs replacement unit.',
            'purchase_date' => now()->toDateString(),
            'warranty_expires_at' => now()->addYear()->toDateString(),
            'covered_by_platform_warranty' => true,
            'status' => WarrantyClaim::STATUS_APPROVED,
        ]);

        $subOrdersBefore = SubOrder::count();

        $replacement = app(\App\Services\WarrantyClaimResolutionService::class)->replace($claim);

        $this->assertSame($subOrdersBefore + 1, SubOrder::count());
        $this->assertNotNull($replacement->id);
        $this->assertSame($order->id, $replacement->order_id);
        $this->assertSame(0, (int) $replacement->items()->first()->unit_price);
    }

    // ── Post-purchase buy flow (P-09 task 4) ─────────────────────────────────

    private function deliverWithoutPlan(MarketplaceScenario $scenario): array
    {
        $cart = $this->cartFor($scenario);
        CartItem::create([
            'cart_id' => $cart->id,
            'vendor_listing_id' => $scenario->vendorListingFbp->id,
            'quantity' => 1,
            'unit_price' => (int) $scenario->vendorListingFbp->getRawOriginal('price'),
            'added_at' => now(),
        ]);

        $order = $this->placeOrder($scenario, 'wallet');
        $subOrder = $order->subOrders()->where('seller_type', 'vendor')->first();
        $subOrder = $this->deliver($subOrder);
        $item = $subOrder->items()->first();

        return [$order, $subOrder, $item];
    }

    public function test_customer_can_buy_warranty_after_delivery_within_window_and_it_activates_immediately(): void
    {
        $scenario = $this->buildScenario();
        [$order, $subOrder, $item] = $this->deliverWithoutPlan($scenario);

        $walletBalanceBefore = $scenario->customerWallet->refresh()->balance;

        $this->actingAs($scenario->customer, 'customer');
        $response = $this->postJson(
            "/api/customer/v1/{$scenario->country->site_code}/warranty/purchases",
            [
                'order_item_id' => $item->id,
                'warranty_plan_id' => $scenario->warrantyPlanFlat->id,
            ],
        );

        $response->assertStatus(201);
        $response->assertJsonPath('data.status', 'active');

        $purchase = WarrantyPurchase::where('order_item_id', $item->id)->first();
        $this->assertNotNull($purchase);
        $this->assertSame('active', $purchase->status);
        $this->assertSame((int) $scenario->warrantyPlanFlat->price, $purchase->price_paid);

        // Same coverage-date formula as SubOrderObserver: vendor has
        // warranty_months = 12, so platform coverage starts 12 months
        // after delivery and runs for the plan's own 12-month duration.
        $expectedStart = $subOrder->delivered_at->copy()->addMonths(12)->toDateString();
        $expectedEnd = $subOrder->delivered_at->copy()->addMonths(12)->addMonths(12)->toDateString();
        $this->assertSame($expectedStart, $purchase->coverage_starts_at->toDateString());
        $this->assertSame($expectedEnd, $purchase->coverage_ends_at->toDateString());

        $this->assertSame($purchase->id, $item->refresh()->warranty_purchase_id);

        $walletBalanceAfter = $scenario->customerWallet->refresh()->balance;
        $this->assertSame($walletBalanceBefore - (int) $scenario->warrantyPlanFlat->price, $walletBalanceAfter);
    }

    public function test_buying_warranty_outside_the_window_returns_422(): void
    {
        $scenario = $this->buildScenario();
        [, $subOrder, $item] = $this->deliverWithoutPlan($scenario);

        $windowDays = (int) config('warranty.post_purchase_window_days', 30);
        $subOrder->update(['delivered_at' => now()->subDays($windowDays + 5)]);

        $this->actingAs($scenario->customer, 'customer');
        $response = $this->postJson(
            "/api/customer/v1/{$scenario->country->site_code}/warranty/purchases",
            [
                'order_item_id' => $item->id,
                'warranty_plan_id' => $scenario->warrantyPlanFlat->id,
            ],
        );

        $response->assertStatus(422);
        $this->assertNull(WarrantyPurchase::where('order_item_id', $item->id)->first());
    }

    public function test_buying_warranty_for_a_non_delivered_item_returns_422(): void
    {
        $scenario = $this->buildScenario();
        $cart = $this->cartFor($scenario);
        CartItem::create([
            'cart_id' => $cart->id,
            'vendor_listing_id' => $scenario->vendorListingFbp->id,
            'quantity' => 1,
            'unit_price' => (int) $scenario->vendorListingFbp->getRawOriginal('price'),
            'added_at' => now(),
        ]);

        $order = $this->placeOrder($scenario, 'wallet');
        $subOrder = $order->subOrders()->where('seller_type', 'vendor')->first();
        // Not delivered — still in an earlier state.
        $item = $subOrder->items()->first();

        $this->actingAs($scenario->customer, 'customer');
        $response = $this->postJson(
            "/api/customer/v1/{$scenario->country->site_code}/warranty/purchases",
            [
                'order_item_id' => $item->id,
                'warranty_plan_id' => $scenario->warrantyPlanFlat->id,
            ],
        );

        $response->assertStatus(422);
        $this->assertNull(WarrantyPurchase::where('order_item_id', $item->id)->first());
    }

    public function test_buying_warranty_when_one_already_pending_or_active_returns_422(): void
    {
        $scenario = $this->buildScenario();
        $cart = $this->cartFor($scenario);
        CartItem::create([
            'cart_id' => $cart->id,
            'vendor_listing_id' => $scenario->vendorListingFbp->id,
            'quantity' => 1,
            'unit_price' => (int) $scenario->vendorListingFbp->getRawOriginal('price'),
            'warranty_plan_id' => $scenario->warrantyPlanFlat->id,
            'added_at' => now(),
        ]);

        $order = $this->placeOrder($scenario, 'wallet');
        $subOrder = $order->subOrders()->where('seller_type', 'vendor')->first();
        $this->deliver($subOrder);
        $item = $subOrder->items()->first();

        // Already has an active warranty from checkout.
        $this->assertNotNull($item->refresh()->warranty_purchase_id);

        $this->actingAs($scenario->customer, 'customer');
        $response = $this->postJson(
            "/api/customer/v1/{$scenario->country->site_code}/warranty/purchases",
            [
                'order_item_id' => $item->id,
                'warranty_plan_id' => $scenario->warrantyPlanPercentage->id,
            ],
        );

        $response->assertStatus(422);
    }

    public function test_failed_payment_does_not_create_or_activate_a_warranty_purchase(): void
    {
        $scenario = $this->buildScenario();
        [, , $item] = $this->deliverWithoutPlan($scenario);

        // Drain the wallet so the debit fails.
        $scenario->customerWallet->update(['balance' => 0]);

        $this->actingAs($scenario->customer, 'customer');
        $response = $this->postJson(
            "/api/customer/v1/{$scenario->country->site_code}/warranty/purchases",
            [
                'order_item_id' => $item->id,
                'warranty_plan_id' => $scenario->warrantyPlanFlat->id,
            ],
        );

        $response->assertStatus(422);
        $this->assertNull(WarrantyPurchase::where('order_item_id', $item->id)->first());
        $this->assertNull($item->refresh()->warranty_purchase_id);
        $this->assertSame(0, $scenario->customerWallet->refresh()->balance);
    }
}
