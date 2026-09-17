<?php

namespace Tests\Feature;

use App\Enums\CancelActor;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItemAllocation;
use App\Models\VendorAdmin;
use App\Models\WarehouseInventory;
use App\Services\Inventory\InsufficientStockException;
use App\Services\Inventory\InventoryService;
use App\Services\OrderCancellationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\AssertsOrderMoney;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * enhancement.md P-13: one InventoryService for every stock increment
 * and decrement. Covers:
 *  - checkout reserve picks the exact row (not "first row of listing")
 *    and never over-reserves one row of a multi-warehouse listing;
 *  - payment-failed / customer-cancel release the exact reserved
 *    allocation;
 *  - vendor-ship commits on_hand and reserved together (no leak);
 *  - concurrent reservation against the same row cannot both succeed
 *    past available stock;
 *  - inventory:reconcile reports zero drift after a normal lifecycle.
 */
class InventoryServiceTest extends TestCase
{
    use RefreshDatabase;
    use AssertsOrderMoney;

    protected function tearDown(): void
    {
        \App\Services\Payments\PaymentGatewayFactory::fake(null);
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

    // ── Reservation targets the exact row, never "first row of listing" ─────

    public function test_checkout_reservation_writes_an_allocation_for_the_exact_row_and_reserves_the_right_amount(): void
    {
        $scenario = $this->buildScenario();
        $cart = $this->cartFor($scenario);
        $this->addVendorItem($scenario, $cart, $scenario->vendorListingFbp, 3);

        $order = $this->placeOrder($scenario, 'wallet');
        $orderItem = $order->items()->first();

        $allocations = $orderItem->allocations;
        $this->assertCount(1, $allocations);
        $this->assertSame('reserved', $allocations->first()->status);
        $this->assertSame(3, $allocations->first()->quantity);

        // Reserved must land on a row that actually belongs to this listing.
        $row = WarehouseInventory::find($allocations->first()->warehouse_inventory_id);
        $this->assertSame($scenario->vendorListingFbp->id, $row->vendor_listing_id);

        $this->assertStock($scenario->vendorListingFbp, 50, 3);
    }

    public function test_multi_warehouse_listing_splits_reservation_across_rows_when_one_row_cannot_cover_it(): void
    {
        $scenario = $this->buildScenario();

        // Shrink the existing row to 2 available and add a second warehouse
        // row with 10 available for the same listing, so an order of 5
        // units cannot be satisfied by either row alone.
        $primaryRow = WarehouseInventory::where('vendor_listing_id', $scenario->vendorListingFbp->id)->first();
        $primaryRow->update(['quantity_on_hand' => 2, 'quantity_reserved' => 0]);

        $secondWarehouse = \App\Models\Warehouse::create([
            'country_id' => $scenario->country->id,
            'name' => 'Second Warehouse',
            'code' => 'WH-'.Str::upper(Str::random(6)),
            'is_active' => true,
        ]);

        $secondRow = WarehouseInventory::create([
            'vendor_listing_id' => $scenario->vendorListingFbp->id,
            'warehouse_id' => $secondWarehouse->id,
            'quantity_on_hand' => 3,
            'quantity_reserved' => 0,
        ]);

        $allocations = app(InventoryService::class)->reserve(
            $scenario->vendorListingFbp,
            5,
            'order',
            (string) Str::uuid(),
            actorType: 'customer',
            actorId: $scenario->customer->id,
        );

        $this->assertCount(2, $allocations);
        $totalReserved = array_sum(array_column($allocations, 'quantity'));
        $this->assertSame(5, $totalReserved);

        $primaryRow->refresh();
        $secondRow->refresh();
        $this->assertSame(2, $primaryRow->quantity_reserved);
        $this->assertSame(3, $secondRow->quantity_reserved);
        $this->assertSame(2, $primaryRow->quantity_on_hand);
        $this->assertSame(3, $secondRow->quantity_on_hand);
    }

    public function test_reserve_throws_and_rolls_back_when_stock_is_insufficient_across_all_rows(): void
    {
        $scenario = $this->buildScenario();
        $row = WarehouseInventory::where('vendor_listing_id', $scenario->vendorListingFbp->id)->first();
        $row->update(['quantity_on_hand' => 3, 'quantity_reserved' => 0]);

        $this->expectException(InsufficientStockException::class);

        try {
            app(InventoryService::class)->reserve(
                $scenario->vendorListingFbp,
                5,
                'order',
                (string) Str::uuid(),
            );
        } finally {
            $row->refresh();
            $this->assertSame(0, $row->quantity_reserved, 'a failed reservation must not leave a partial reserved delta');
        }
    }

    // ── Release targets the exact reserved allocation ───────────────────────

    public function test_payment_failed_releases_exact_reserved_allocation(): void
    {
        $scenario = $this->buildScenario();
        $fake = new \Tests\Support\FakePaymentGateway();
        $fake->scriptDefaultInitiate(\Tests\Support\FakePaymentGateway::OUTCOME_SUCCESS);
        \App\Services\Payments\PaymentGatewayFactory::fake($fake);

        $cart = $this->cartFor($scenario);
        $this->addVendorItem($scenario, $cart, $scenario->vendorListingFbp, 2);

        $order = $this->placeOrder($scenario, 'stripe', [
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $this->assertStock($scenario->vendorListingFbp, 50, 2);

        app(\App\Services\Checkout\CheckoutRollbackService::class)->rollback($order, 'Payment failed');

        $this->assertStock($scenario->vendorListingFbp, 50, 0);

        $allocation = OrderItemAllocation::where('order_item_id', $order->items()->first()->id)->first();
        $this->assertSame('released', $allocation->status);
    }

    public function test_customer_cancel_releases_exact_reserved_allocation_not_first_row_of_listing(): void
    {
        $scenario = $this->buildScenario();

        // A second, unrelated warehouse row for the same listing that must
        // NOT be touched by the release.
        $otherWarehouse = \App\Models\Warehouse::create([
            'country_id' => $scenario->country->id,
            'name' => 'Other Warehouse',
            'code' => 'WH-'.Str::upper(Str::random(6)),
            'is_active' => true,
        ]);
        $otherRow = WarehouseInventory::create([
            'vendor_listing_id' => $scenario->vendorListingFbp->id,
            'warehouse_id' => $otherWarehouse->id,
            'quantity_on_hand' => 100,
            'quantity_reserved' => 7,
        ]);

        $cart = $this->cartFor($scenario);
        $this->addVendorItem($scenario, $cart, $scenario->vendorListingFbp, 1);
        $order = $this->placeOrder($scenario, 'wallet');

        app(OrderCancellationService::class)->cancel($order, CancelActor::Customer, 'Changed my mind');

        $this->assertStock($scenario->vendorListingFbp, 50, 0);

        $otherRow->refresh();
        $this->assertSame(7, $otherRow->quantity_reserved, 'the unrelated warehouse row must be untouched');
    }

    // ── Ship commits on_hand and reserved together (no leak) ────────────────

    public function test_vendor_ship_commits_on_hand_and_reserved_together(): void
    {
        $scenario = $this->buildScenario();
        $cart = $this->cartFor($scenario);
        $this->addVendorItem($scenario, $cart, $scenario->vendorListingFbp, 4);
        $order = $this->placeOrder($scenario, 'wallet');

        $subOrder = $order->subOrders()->where('seller_type', 'vendor')->first();

        $machine = app(\App\Services\OrderStateMachine::class);
        foreach (['confirmed', 'processing'] as $step) {
            $machine->transition($subOrder, $step, CancelActor::System);
        }

        $scenario->vendor->update(['onboarding_completed_at' => now()]);

        $vendorAdmin = VendorAdmin::create([
            'vendor_id' => $scenario->vendor->id,
            'name' => 'Ship Test Admin',
            'email' => 'vendor-admin-'.Str::random(8).'@example.com',
            'password' => bcrypt('password'),
            'role' => 'owner',
            'is_owner' => true,
            'is_active' => true,
        ]);
        $permission = Permission::firstOrCreate(['name' => 'orders.process', 'guard_name' => 'vendor']);
        $vendorAdmin->givePermissionTo($permission);
        $this->actingAs($vendorAdmin, 'vendor');

        $this->assertStock($scenario->vendorListingFbp, 50, 4);

        \Illuminate\Support\Facades\Notification::fake();

        $response = $this->postJson(route('vendor.orders.ship', $subOrder->sub_order_number), [
            'tracking_number' => 'TRACK-123',
        ]);
        $response->assertStatus(200);

        // on_hand and reserved drop together — never independently.
        $this->assertStock($scenario->vendorListingFbp, 46, 0);

        $allocation = OrderItemAllocation::where('order_item_id', $order->items()->where('vendor_listing_id', $scenario->vendorListingFbp->id)->first()->id)->first();
        $this->assertSame('committed', $allocation->status);

        $movement = \App\Models\InventoryMovement::where('warehouse_inventory_id', $allocation->warehouse_inventory_id)
            ->where('movement_type', 'outbound')
            ->latest('created_at')
            ->first();
        $this->assertNotNull($movement);
        $this->assertSame(-4, $movement->quantity_delta);
    }

    // ── Concurrency: only one of two racing reservations can win the last unit ──

    public function test_concurrent_reservations_against_the_same_row_cannot_both_succeed_past_available_stock(): void
    {
        $scenario = $this->buildScenario();
        $row = WarehouseInventory::where('vendor_listing_id', $scenario->vendorListingFbp->id)->first();
        $row->update(['quantity_on_hand' => 1, 'quantity_reserved' => 0]);

        $service = app(InventoryService::class);

        $service->reserve($scenario->vendorListingFbp, 1, 'order', (string) Str::uuid());

        // A second reservation for the same (now fully reserved) row must
        // fail rather than driving reserved above on_hand.
        $this->expectException(InsufficientStockException::class);
        $service->reserve($scenario->vendorListingFbp, 1, 'order', (string) Str::uuid());
    }

    // ── inventory:reconcile ──────────────────────────────────────────────────

    public function test_inventory_reconcile_reports_zero_drift_after_reserve_and_release(): void
    {
        $scenario = $this->buildScenario();
        $cart = $this->cartFor($scenario);
        $this->addVendorItem($scenario, $cart, $scenario->vendorListingFbp, 2);
        $order = $this->placeOrder($scenario, 'wallet');

        app(OrderCancellationService::class)->cancel($order, CancelActor::Customer, 'test');

        $this->artisan('inventory:reconcile')
            ->expectsOutputToContain('No drift found.')
            ->assertExitCode(0);
    }
}
