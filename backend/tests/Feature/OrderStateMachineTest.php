<?php

namespace Tests\Feature;

use App\Enums\CancelActor;
use App\Exceptions\InvalidOrderTransitionException;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\DeliveryAgent;
use App\Models\DeliveryAssignment;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\SubOrder;
use App\Services\Delivery\AssignmentService;
use App\Services\OrderStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * enhancement.md P-08: order/sub-order status state machine, unified
 * delivery (mobile API + web panel), COD capture on delivery, and
 * return_eligible_until read from categories.return_window_days.
 */
class OrderStateMachineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Delivery/shipment notifications build a `route('customer.orders.show', ...)`
        // URL that relies on an active HTTP route context; fake notifications so
        // these background jobs (dispatched synchronously under QUEUE_CONNECTION=sync
        // in tests) don't throw outside of a real request.
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

    private function makeAssignment(SubOrder $subOrder, DeliveryAgent $agent): DeliveryAssignment
    {
        $carrier = \App\Models\ShippingCarrier::create([
            'name' => 'Test Carrier',
            'code' => 'test-carrier-'.Str::random(6),
            'is_active' => true,
        ]);

        $shipment = \App\Models\Shipment::create([
            'sub_order_id' => $subOrder->id,
            'carrier_id'   => $carrier->id,
            'tracking_number' => 'TRK-'.Str::random(10),
            'weight_grams' => 500,
            'shipping_cost_actual' => 0,
            'status'       => 'picked_up',
            'picked_up_at' => now(),
        ]);

        return DeliveryAssignment::create([
            'shipment_id'   => $shipment->id,
            'sub_order_id'  => $subOrder->id,
            'agent_id'      => $agent->id,
            'status'        => DeliveryAssignment::STATUS_PICKED_UP,
            'assigned_at'   => now(),
            'accepted_at'   => now(),
            'picked_up_at'  => now(),
            'delivery_otp'  => '123456',
            'otp_attempts'  => 0,
        ]);
    }

    // ── Illegal transition ──────────────────────────────────────────────────

    public function test_illegal_transition_throws_domain_exception(): void
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
        $subOrder = $order->subOrders()->first();

        $this->assertSame('placed', $subOrder->status->value);

        $this->expectException(InvalidOrderTransitionException::class);
        app(OrderStateMachine::class)->transition($subOrder, 'delivered', CancelActor::System);
    }

    public function test_partner_mark_delivered_endpoint_rejects_illegal_jump_from_placed(): void
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
        $this->assertSame('placed', $subOrder->status->value);

        $vendorAdmin = \App\Models\VendorAdmin::create([
            'vendor_id' => $scenario->vendor->id,
            'name' => 'Test Vendor Admin',
            'email' => 'vendor-admin-'.Str::random(8).'@example.com',
            'password' => bcrypt('password'),
            'role' => 'owner',
            'is_owner' => true,
            'is_active' => true,
        ]);
        $permission = \Spatie\Permission\Models\Permission::firstOrCreate(
            ['name' => 'orders.process', 'guard_name' => 'vendor']
        );
        $vendorAdmin->givePermissionTo($permission);

        $this->actingAs($vendorAdmin, 'vendor');

        $response = $this->postJson(route('partner.orders.deliver', $subOrder->sub_order_number));

        $response->assertStatus(422);
        $subOrder->refresh();
        $this->assertSame('placed', $subOrder->status->value, 'illegal jump must be rejected, not silently applied');
    }

    // ── 2-vendor rollup progression ─────────────────────────────────────────

    public function test_two_vendor_order_rolls_up_through_every_stage(): void
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
        CartItem::create([
            'cart_id' => $cart->id,
            'admin_listing_id' => $scenario->adminListing->id,
            'quantity' => 1,
            'unit_price' => (int) $scenario->adminListing->getRawOriginal('price'),
            'added_at' => now(),
        ]);

        $order = $this->placeOrder($scenario, 'cod');
        $order->refresh();
        $this->assertSame('placed', $order->status->value);

        $subOrders = $order->subOrders()->get();
        $this->assertCount(2, $subOrders, 'one vendor sub-order + one platform sub-order');
        [$subA, $subB] = [$subOrders[0], $subOrders[1]];

        $machine = app(OrderStateMachine::class);

        // Move sub A through to shipped -> order partially_shipped.
        foreach (['confirmed', 'processing', 'packed', 'shipped'] as $step) {
            $machine->transition($subA, $step, CancelActor::System);
        }
        $order->refresh();
        $this->assertSame('partially_shipped', $order->status->value);

        // Ship sub B too -> order fully shipped.
        foreach (['confirmed', 'processing', 'packed', 'shipped'] as $step) {
            $machine->transition($subB, $step, CancelActor::System);
        }
        $order->refresh();
        $this->assertSame('shipped', $order->status->value);

        // Deliver sub A -> order partially_delivered.
        $machine->transition($subA, 'delivered', CancelActor::System);
        $order->refresh();
        $this->assertSame('partially_delivered', $order->status->value);

        // Deliver sub B -> order delivered.
        $machine->transition($subB, 'delivered', CancelActor::System);
        $order->refresh();
        $this->assertSame('delivered', $order->status->value);

        // Complete both -> order completed.
        $machine->transition($subA, 'completed', CancelActor::System);
        $machine->transition($subB, 'completed', CancelActor::System);
        $order->refresh();
        $this->assertSame('completed', $order->status->value);

        $this->assertDatabaseHas('order_status_histories', [
            'sub_order_id' => $subA->id,
            'from_status'  => 'placed',
            'to_status'    => 'confirmed',
        ]);
    }

    // ── COD capture on delivery (identical for both delivery paths) ────────

    public function test_cod_captured_once_all_sub_orders_delivered_via_assignment_service(): void
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

        $order = $this->placeOrder($scenario, 'cod');
        $this->assertNotSame('captured', $order->payment_status->value);

        $subOrder = $order->subOrders()->first();
        $machine = app(OrderStateMachine::class);
        foreach (['confirmed', 'processing', 'packed', 'shipped'] as $step) {
            $machine->transition($subOrder, $step, CancelActor::System);
        }

        $assignment = $this->makeAssignment($subOrder, $scenario->deliveryAgent);

        app(AssignmentService::class)->deliver(
            $assignment,
            $scenario->deliveryAgent,
            '123456',
            null,
            25.2,
            55.3,
            (int) $order->total,
        );

        $order->refresh();
        $subOrder->refresh();

        $this->assertSame('delivered', $subOrder->status->value);
        $this->assertSame('captured', $order->payment_status->value, 'COD must be captured on delivery via the mobile/AssignmentService path');

        $this->assertDatabaseHas('payment_transactions', [
            'order_id' => $order->id,
            'gateway'  => 'cod',
            'status'   => 'succeeded',
        ]);

        $item = $subOrder->items()->first();
        $this->assertNotNull($item->return_eligible_until);
        $this->assertSame('delivered', $item->fulfillment_status->value);
    }

    public function test_return_window_days_read_from_category_setting_not_hardcoded_14(): void
    {
        $scenario = $this->buildScenario();
        $scenario->category->update(['return_window_days' => 30]);

        $cart = $this->cartFor($scenario);
        CartItem::create([
            'cart_id' => $cart->id,
            'vendor_listing_id' => $scenario->vendorListingFbp->id,
            'quantity' => 1,
            'unit_price' => (int) $scenario->vendorListingFbp->getRawOriginal('price'),
            'added_at' => now(),
        ]);

        $order = $this->placeOrder($scenario, 'wallet');
        $subOrder = $order->subOrders()->first();
        $machine = app(OrderStateMachine::class);
        foreach (['confirmed', 'processing', 'packed', 'shipped', 'delivered'] as $step) {
            $machine->transition($subOrder, $step, CancelActor::System);
        }

        $item = $subOrder->items()->first()->refresh();
        $this->assertEqualsWithDelta(
            now()->addDays(30)->toDateString(),
            $item->return_eligible_until->toDateString(),
            0
        );
    }

    // ── Identical DB state: mobile API service call vs web panel controller ─

    public function test_mobile_and_web_delivery_paths_produce_identical_shape(): void
    {
        $scenario = $this->buildScenario();

        // Order 1 (delivered via AssignmentService directly — the mobile API path).
        $cart1 = $this->cartFor($scenario);
        CartItem::create([
            'cart_id' => $cart1->id,
            'vendor_listing_id' => $scenario->vendorListingFbp->id,
            'quantity' => 1,
            'unit_price' => (int) $scenario->vendorListingFbp->getRawOriginal('price'),
            'added_at' => now(),
        ]);
        $order1 = $this->placeOrder($scenario, 'cod');
        $subOrder1 = $order1->subOrders()->first();
        $machine = app(OrderStateMachine::class);
        foreach (['confirmed', 'processing', 'packed', 'shipped'] as $step) {
            $machine->transition($subOrder1, $step, CancelActor::System);
        }
        $assignment1 = $this->makeAssignment($subOrder1, $scenario->deliveryAgent);
        app(AssignmentService::class)->deliver($assignment1, $scenario->deliveryAgent, '123456', null, 25.0, 55.0, (int) $order1->total);

        // Order 2 (delivered via the web panel controller, same shape).
        $cart2 = $this->cartFor($scenario);
        CartItem::create([
            'cart_id' => $cart2->id,
            'vendor_listing_id' => $scenario->vendorListingFbp->id,
            'quantity' => 1,
            'unit_price' => (int) $scenario->vendorListingFbp->getRawOriginal('price'),
            'added_at' => now(),
        ]);
        $order2 = $this->placeOrder($scenario, 'cod');
        $subOrder2 = $order2->subOrders()->first();
        foreach (['confirmed', 'processing', 'packed', 'shipped'] as $step) {
            $machine->transition($subOrder2, $step, CancelActor::System);
        }
        $assignment2 = $this->makeAssignment($subOrder2, $scenario->deliveryAgent);

        Auth::guard('delivery')->login($scenario->deliveryAgent);
        $request = \Illuminate\Http\Request::create(
            "/assignments/{$assignment2->id}/deliver",
            'POST',
            [
                'otp_code' => '123456',
                'cod_amount_collected' => (int) $order2->total,
            ]
        );
        $controller = app(\App\Http\Controllers\Delivery\AssignmentController::class);
        $response = $controller->deliver($request, $assignment2);
        $this->assertSame(200, $response->getStatusCode());

        $order1->refresh();
        $order2->refresh();
        $subOrder1->refresh();
        $subOrder2->refresh();

        $this->assertSame($order1->status->value, $order2->status->value);
        $this->assertSame($order1->payment_status->value, $order2->payment_status->value);
        $this->assertSame($subOrder1->status->value, $subOrder2->status->value);
        $this->assertSame(
            $subOrder1->items()->first()->fulfillment_status->value,
            $subOrder2->items()->first()->fulfillment_status->value,
        );

        $pt1 = PaymentTransaction::where('order_id', $order1->id)->where('gateway', 'cod')->first();
        $pt2 = PaymentTransaction::where('order_id', $order2->id)->where('gateway', 'cod')->first();
        $this->assertSame($pt1->status->value, $pt2->status->value);
    }
}
