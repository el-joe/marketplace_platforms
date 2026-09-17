<?php

namespace Tests\Feature;

use App\Enums\CancelActor;
use App\Enums\ReturnRequestStatus;
use App\Models\CartItem;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\SubOrder;
use App\Models\WarehouseInventory;
use App\Services\OrderStateMachine;
use App\Services\ReturnRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * enhancement.md P-10: return (listing return) lifecycle — eligibility
 * checks (delivered, within window, quantity, category), restock to the
 * ORIGINAL warehouse row, an items-only refund, and the exchange
 * replacement path.
 */
class ReturnRequestLifecycleTest extends TestCase
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

    private function placeOrder(MarketplaceScenario $scenario, string $gatewayCode): Order
    {
        $response = $this->postJson("/api/customer/v1/{$scenario->country->site_code}/checkout/place-order", [
            'address_id' => $scenario->customerAddress->id,
            'country_payment_gateway_id' => $scenario->countryPaymentGateways[$gatewayCode]->id,
            'idempotency_key' => (string) Str::uuid(),
        ]);
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

    private function deliveredVendorOrder(MarketplaceScenario $scenario, int $qty = 2): array
    {
        $cart = $this->cartFor($scenario);
        CartItem::create([
            'cart_id' => $cart->id,
            'vendor_listing_id' => $scenario->vendorListingFbp->id,
            'quantity' => $qty,
            'unit_price' => (int) $scenario->vendorListingFbp->getRawOriginal('price'),
            'added_at' => now(),
        ]);

        $order = $this->placeOrder($scenario, 'wallet');
        $subOrder = $order->subOrders()->where('seller_type', 'vendor')->first();
        $subOrder = $this->deliver($subOrder);
        $item = $subOrder->items()->first();

        return [$order, $subOrder, $item];
    }

    // ── Eligibility: 422s ───────────────────────────────────────────────────

    public function test_return_before_delivery_is_rejected(): void
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
        $item = $subOrder->items()->first();

        $this->expectException(ValidationException::class);

        app(ReturnRequestService::class)->create(
            customer: $scenario->customer,
            orderItemIds: [$item->id],
            reason: 'changed_mind',
            returnType: 'refund',
        );
    }

    public function test_return_after_window_is_rejected(): void
    {
        $scenario = $this->buildScenario();
        [$order, $subOrder, $item] = $this->deliveredVendorOrder($scenario);
        $item->update(['return_eligible_until' => now()->subDay()->toDateString()]);

        $this->expectException(ValidationException::class);

        app(ReturnRequestService::class)->create(
            customer: $scenario->customer,
            orderItemIds: [$item->id],
            reason: 'changed_mind',
            returnType: 'refund',
        );
    }

    public function test_return_for_quantity_above_purchased_is_rejected(): void
    {
        $scenario = $this->buildScenario();
        [$order, $subOrder, $item] = $this->deliveredVendorOrder($scenario, 2);

        $this->expectException(ValidationException::class);

        app(ReturnRequestService::class)->create(
            customer: $scenario->customer,
            orderItemIds: [$item->id],
            reason: 'changed_mind',
            returnType: 'refund',
            quantities: [$item->id => 5],
        );
    }

    public function test_already_returned_item_cannot_be_returned_again(): void
    {
        $scenario = $this->buildScenario();
        [$order, $subOrder, $item] = $this->deliveredVendorOrder($scenario, 2);

        app(ReturnRequestService::class)->create(
            customer: $scenario->customer,
            orderItemIds: [$item->id],
            reason: 'changed_mind',
            returnType: 'refund',
            quantities: [$item->id => 2],
        );

        $this->expectException(ValidationException::class);

        app(ReturnRequestService::class)->create(
            customer: $scenario->customer,
            orderItemIds: [$item->id],
            reason: 'changed_mind',
            returnType: 'refund',
            quantities: [$item->id => 1],
        );
    }

    public function test_non_returnable_category_is_rejected(): void
    {
        $scenario = $this->buildScenario();
        [$order, $subOrder, $item] = $this->deliveredVendorOrder($scenario);
        $scenario->category->update(['is_returnable' => false]);

        $this->expectException(ValidationException::class);

        app(ReturnRequestService::class)->create(
            customer: $scenario->customer,
            orderItemIds: [$item->id],
            reason: 'changed_mind',
            returnType: 'refund',
        );
    }

    // ── HTTP 422 via the actual customer endpoint ───────────────────────────

    public function test_api_customer_return_endpoint_returns_422_before_delivery(): void
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
        $item = $subOrder->items()->first();

        $this->actingAs($scenario->customer, 'customer');
        $response = $this->postJson('/api/customer/v1/return-requests', [
            'order_item_ids' => [$item->id],
            'reason' => 'changed_mind',
            'return_type' => 'refund',
        ]);

        $response->assertStatus(422);
    }

    // ── Restock + refund ─────────────────────────────────────────────────────

    public function test_good_inspection_restocks_original_warehouse_row_and_refunds_item_share(): void
    {
        $scenario = $this->buildScenario();
        [$order, $subOrder, $item] = $this->deliveredVendorOrder($scenario, 2);

        $inventory = WarehouseInventory::where('vendor_listing_id', $scenario->vendorListingFbp->id)
            ->where('warehouse_id', $subOrder->warehouse_id)
            ->first();
        $onHandBefore = $inventory->quantity_on_hand;

        $service = app(ReturnRequestService::class);
        $returnRequests = $service->create(
            customer: $scenario->customer,
            orderItemIds: [$item->id],
            reason: 'changed_mind',
            returnType: 'refund',
            quantities: [$item->id => 1],
        );
        $returnRequest = $returnRequests->first();
        $this->assertSame($subOrder->id, $returnRequest->sub_order_id);

        $admin = \App\Models\Admin::factory()->create();

        $service->approve($returnRequest, (string) $admin->id);
        $service->schedulePickup($returnRequest, null);
        $service->markReceived($returnRequest);
        $service->inspect($returnRequest, 'good', [], null, (string) $admin->id);

        $returnRequest->refresh();
        $this->assertSame(ReturnRequestStatus::Completed, $returnRequest->status);

        // Restocked to the ORIGINAL warehouse row.
        $inventory->refresh();
        $this->assertSame($onHandBefore + 1, $inventory->quantity_on_hand);

        $movement = InventoryMovement::where('warehouse_inventory_id', $inventory->id)
            ->where('reference_id', $returnRequest->id)
            ->first();
        $this->assertNotNull($movement);
        $this->assertSame('return', $movement->movement_type->value);
        $this->assertSame('return', $movement->reference_type->value);

        // Refund equals exactly the ONE returned unit's share, not the
        // whole (2-unit) sub-order.
        $this->assertNotNull($returnRequest->refund_id);
        $refund = \App\Models\Refund::find($returnRequest->refund_id);
        $lineUnitTotal = (int) $item->line_total / (int) $item->quantity;
        $this->assertEquals((int) round($lineUnitTotal), (int) $refund->amount);
        $this->assertLessThan((int) $item->line_total, (int) $refund->amount);
    }

    public function test_exchange_return_creates_replacement_sub_order_instead_of_refund(): void
    {
        $scenario = $this->buildScenario();
        [$order, $subOrder, $item] = $this->deliveredVendorOrder($scenario, 1);

        $service = app(ReturnRequestService::class);
        $returnRequests = $service->create(
            customer: $scenario->customer,
            orderItemIds: [$item->id],
            reason: 'defective',
            returnType: 'exchange',
        );
        $returnRequest = $returnRequests->first();

        $admin = \App\Models\Admin::factory()->create();
        $service->approve($returnRequest, (string) $admin->id);
        $service->schedulePickup($returnRequest, null);
        $service->markReceived($returnRequest);

        $subOrdersBefore = SubOrder::count();
        $service->inspect($returnRequest, 'good', [], null, (string) $admin->id);

        $returnRequest->refresh();
        $this->assertSame(ReturnRequestStatus::Completed, $returnRequest->status);
        $this->assertNull($returnRequest->refund_id, 'an exchange must not create a refund');
        $this->assertSame($subOrdersBefore + 1, SubOrder::count());
    }
}
