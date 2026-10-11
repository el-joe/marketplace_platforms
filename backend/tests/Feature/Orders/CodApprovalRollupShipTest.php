<?php

namespace Tests\Feature\Orders;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Shipment;
use App\Models\ShippingCarrier;
use App\Services\OrderInterventionService;
use App\Services\OrderStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

class CodApprovalRollupShipTest extends TestCase
{
    use RefreshDatabase;

    private function place(string $gateway): array
    {
        Notification::fake();
        $s = MarketplaceScenario::make()->build();
        $s->country->update(['site_code' => 'ae-'.Str::lower(Str::random(6))]);
        $this->actingAs($s->customer, 'customer');
        $cart = app(\App\Services\Customer\CartService::class)
            ->getOrCreateCart($s->customer, $s->country->id, $s->country->currency_code);
        CartItem::create([
            'cart_id' => $cart->id,
            'vendor_listing_id' => $s->vendorListingFbp->id,
            'quantity' => 1,
            'unit_price' => (int) $s->vendorListingFbp->getRawOriginal('price'),
            'added_at' => now(),
        ]);
        $r = $this->postJson("/api/customer/v1/{$s->country->site_code}/checkout/place-order", [
            'address_id' => $s->customerAddress->id,
            'country_payment_gateway_id' => $s->countryPaymentGateways[$gateway]->id,
            'idempotency_key' => (string) Str::uuid(),
        ]);
        $r->assertStatus(201);
        $n = $r->json('data.order.order_number') ?? $r->json('data.order_number');

        return [$s, Order::where('order_number', $n)->firstOrFail()];
    }

    private function carrier(MarketplaceScenario $s): ShippingCarrier
    {
        return ShippingCarrier::create([
            'country_id' => $s->country->id,
            'name' => 'Carrier',
            'code' => Str::random(6),
            'is_active' => true,
        ]);
    }

    public function test_cod_with_offline_gateway_type_is_not_blocked_but_bank_transfer_is(): void
    {
        [$s, $cod] = $this->place('cod');
        $s->paymentGateways['cod']->update(['type' => 'offline']);
        app(OrderInterventionService::class)->updateOrderStatus($cod, 'confirmed', 'test', (string) Str::uuid());
        $this->assertSame('confirmed', $cod->fresh()->status->value);

    }

    public function test_bank_transfer_pending_is_still_blocked(): void
    {
        [$s, $bank] = $this->place('bank_transfer');
        $this->expectException(ValidationException::class);
        app(OrderInterventionService::class)->updateOrderStatus($bank, 'confirmed', 'test', (string) Str::uuid());
    }

    public function test_rollup_placed_to_confirmed(): void
    {
        [$s, $order] = $this->place('cod');
        $order->subOrders()->update(['status' => 'processing']);

        $order->refresh()->syncStatusFromSubOrders();

        $this->assertSame('confirmed', $order->fresh()->status->value);
        $this->assertTrue(OrderStatusHistory::where('order_id', $order->id)->whereNull('sub_order_id')->where('to_status', 'confirmed')->exists());
    }

    public function test_reassigning_same_carrier_writes_no_history(): void
    {
        [$s, $order] = $this->place('cod');
        $sub = $order->subOrders()->first();
        $carrier = $this->carrier($s);
        $sub->update(['carrier_id' => $carrier->id, 'shipping_method_id' => \App\Models\ShippingMethod::query()->value('id') ?? (string) Str::uuid()]);
        $before = OrderStatusHistory::count();

        $r = app(\App\Http\Controllers\Admin\OrderController::class)->assignShippingMethod(
            \Illuminate\Http\Request::create('/', 'POST', ['carrier_id' => $carrier->id]),
            $sub->fresh(),
        );

        $this->assertTrue($r->getData(true)['unchanged'] ?? false, $r->getContent());
        $this->assertSame($before, OrderStatusHistory::count());
    }

    public function test_admin_ship_creates_unassigned_shipment(): void
    {
        [$s, $order] = $this->place('cod');
        $sub = $order->subOrders()->first();
        $sub->update(['status' => 'confirmed', 'carrier_id' => $this->carrier($s)->id]);

        app(\App\Services\Shipping\ShipmentService::class)
            ->createForSubOrder($sub->fresh('items'), 'TRK-1', \App\Enums\CancelActor::Admin, [], true);

        $this->assertSame('shipped', $sub->fresh()->status->value);
        $this->assertTrue(Shipment::whereDoesntHave('deliveryAssignment')->where('sub_order_id', $sub->id)->exists());
    }

    public function test_admin_ship_route_requires_orders_edit_and_creates_shipment(): void
    {
        [$s, $order] = $this->place('cod');
        $carrier = $this->carrier($s);
        $sub = $order->subOrders()->first();
        $sub->update(['status' => 'confirmed', 'carrier_id' => $carrier->id]);
        $url = route('admin.orders.sub-orders.ship', $sub->id);

        foreach (['orders.view', 'orders.edit', 'vendors.assigned_only'] as $p) {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $p, 'guard_name' => 'admin']);
        }
        $viewer = \App\Models\Admin::factory()->create();
        $viewer->givePermissionTo('orders.view');
        $editor = \App\Models\Admin::factory()->create();
        $editor->givePermissionTo(['orders.view', 'orders.edit']);

        $this->actingAs($viewer, 'admin')->postJson($url, ['carrier_id' => $carrier->id])->assertForbidden();
        $this->assertSame(0, Shipment::where('sub_order_id', $sub->id)->count());

        $this->actingAs($editor, 'admin')->postJson($url, ['carrier_id' => $carrier->id])->assertSuccessful();
        $this->assertTrue(Shipment::whereDoesntHave('deliveryAssignment')->where('sub_order_id', $sub->id)->exists());
    }
}
