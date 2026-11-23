<?php

namespace Tests\Feature\Orders;

use App\Enums\CancelActor;
use App\Models\Admin;
use App\Models\AdminListing;
use App\Models\CartItem;
use App\Models\DeliveryAssignment;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Refund;
use App\Models\Shipment;
use App\Models\ShippingCarrier;
use App\Models\SubOrder;
use App\Models\VendorAdmin;
use App\Models\VendorListing;
use App\Notifications\Customer\OrderCancelled;
use App\Notifications\Customer\OrderRefunded;
use App\Notifications\Customer\OrderRefundProcessed;
use App\Services\Customer\CartService;
use App\Services\LedgerService;
use App\Services\OrderCancellationService;
use App\Services\Payments\PaymentGatewayFactory;
use App\Services\Shipping\ShipmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\AssertsOrderMoney;
use Tests\Support\FakePaymentGateway;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * WS2 (#36, #37, #39): every cancel entry point (admin items / sub-order status / master status / force,
 * vendor panel) goes through OrderCancellationService and cascades items -> sub-order -> master order,
 * settles the pending COD collection, closes open deliveries, and refunds (and says "refund") only when
 * money was actually collected — exactly once.
 */
class OrderCancellationCascadeTest extends TestCase
{
    use AssertsOrderMoney;
    use RefreshDatabase;

    private MarketplaceScenario $scenario;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->scenario = MarketplaceScenario::make()->build();
        $this->scenario->country->update(['site_code' => 'om-'.Str::lower(Str::random(6))]);
    }

    protected function tearDown(): void
    {
        PaymentGatewayFactory::fake(null);
        parent::tearDown();
    }

    // ── Admin "cancel selected items" ───────────────────────────────────

    public function test_cancelling_every_item_of_an_unpaid_cod_order_cancels_sub_order_order_and_cod_without_refund(): void
    {
        $order = $this->placeOrder('cod', [$this->scenario->vendorListingFbp]);
        $subOrder = $order->subOrders->first();

        $this->actingAs($this->admin(), 'admin')
            ->postJson(route('admin.orders.cancel-items', $order->id), [
                'item_ids' => $subOrder->items->pluck('id')->all(),
                'cancel_reason' => 'customer called',
            ])
            ->assertOk()
            ->assertJsonPath('message', __('admin.orders.items_cancelled_no_refund'));

        $this->assertSame('cancelled', $subOrder->fresh()->status->value);
        $this->assertSame('cancelled', $order->fresh()->status->value);
        $this->assertSame('pending', $order->fresh()->payment_status->value);
        $this->assertSame('cancelled', $this->codTransaction($order)->status->value);
        $this->assertStock($this->scenario->vendorListingFbp, 50, 0);
        $this->assertSame(0, Refund::where('order_id', $order->id)->count());

        Notification::assertSentTo($this->scenario->customer, OrderCancelled::class);
        Notification::assertNotSentTo($this->scenario->customer, OrderRefunded::class);
        Notification::assertNotSentTo($this->scenario->customer, OrderRefundProcessed::class);
    }

    public function test_cancelling_some_items_keeps_statuses_and_reduces_the_cod_amount_to_collect(): void
    {
        $order = $this->placeOrder('cod', [$this->scenario->vendorListingFbp, $this->scenario->vendorListingFbn]);
        $subOrder = $order->subOrders->first();
        $this->assertCount(2, $subOrder->items);
        $item = $subOrder->items->firstWhere('vendor_listing_id', $this->scenario->vendorListingFbp->id);
        $codAmountBefore = (float) $this->codTransaction($order)->amount;

        $this->actingAs($this->admin(), 'admin')
            ->postJson(route('admin.orders.cancel-items', $order->id), [
                'item_ids' => [$item->id],
                'cancel_reason' => 'one item unwanted',
            ])
            ->assertOk();

        $this->assertSame('cancelled', $item->fresh()->fulfillment_status->value);
        $this->assertSame('placed', $subOrder->fresh()->status->value);
        $this->assertSame('placed', $order->fresh()->status->value);
        $cod = $this->codTransaction($order);
        $this->assertSame('pending', $cod->status->value);
        $this->assertEqualsWithDelta($codAmountBefore - (float) $item->line_total, (float) $cod->amount, 0.0001);
        $this->assertStock($this->scenario->vendorListingFbp, 50, 0);
        $this->assertStock($this->scenario->vendorListingFbn, 40, 1);

        Notification::assertSentTo(
            $this->scenario->customer,
            OrderCancelled::class,
            fn (OrderCancelled $notification) => $notification->notificationData($this->scenario->customer)['partial'] === true,
        );
        Notification::assertNotSentTo($this->scenario->customer, OrderRefunded::class);
    }

    public function test_cancelling_a_prepaid_card_order_refunds_exactly_once_and_notifies_the_refund(): void
    {
        $gateway = new FakePaymentGateway;
        $gateway->scriptDefaultInitiate(FakePaymentGateway::OUTCOME_SUCCESS);
        $gateway->scriptDefaultRefund(FakePaymentGateway::OUTCOME_REFUND_SUCCESS);
        PaymentGatewayFactory::fake($gateway);

        $order = $this->placeOrder('stripe', [$this->scenario->vendorListingFbp]);
        PaymentTransaction::where('order_id', $order->id)->where('gateway', 'stripe')->update(['status' => 'succeeded']);
        $order->update(['payment_status' => 'captured']);
        app(LedgerService::class)->postOrderCapture($order, (float) $order->total);

        $this->actingAs($this->admin(), 'admin')
            ->postJson(route('admin.orders.cancel-items', $order->id), [
                'item_ids' => $order->items()->pluck('id')->all(),
                'cancel_reason' => 'out of stock',
            ])
            ->assertOk()
            ->assertJsonPath('message', __('admin.orders.items_cancelled_successfully'));

        // A repeated cancel of the whole order must not refund or restock again.
        app(OrderCancellationService::class)->cancel($order->fresh(), CancelActor::Admin, 'again', true);

        $order->refresh();
        $this->assertSame('cancelled', $order->status->value);
        $this->assertSame('refunded', $order->payment_status->value);
        $refunds = Refund::where('order_id', $order->id)->get();
        $this->assertCount(1, $refunds);
        $this->assertSame('completed', $refunds->first()->status->value);
        $this->assertEqualsWithDelta((float) $order->total, (float) $refunds->first()->amount, 0.0001);
        $this->assertCount(1, $gateway->refundCalls);
        $this->assertStock($this->scenario->vendorListingFbp, 50, 0);
        $this->assertSame(1, LedgerEntry::where('reference_type', 'order_capture_reversal')->where('reference_id', $order->id)->distinct()->count('transaction_group_id'));

        Notification::assertSentToTimes($this->scenario->customer, OrderRefundProcessed::class, 1);
        Notification::assertNotSentTo($this->scenario->customer, OrderRefunded::class);
    }

    // ── Admin master / sub-order status ─────────────────────────────────

    public function test_admin_setting_the_master_order_to_cancelled_cascades_to_every_sub_order_item_and_cod(): void
    {
        $order = $this->placeOrder('cod', [$this->scenario->vendorListingFbp, $this->scenario->adminListing]);
        $this->assertCount(2, $order->subOrders);

        $this->actingAs($this->admin(), 'admin')
            ->postJson(route('admin.orders.update-status', $order->id), ['new_status' => 'cancelled', 'reason' => 'fraud check failed'])
            ->assertOk();

        $order->refresh()->load('subOrders.items');
        $this->assertSame('cancelled', $order->status->value);
        $this->assertSame(['cancelled'], $order->subOrders->pluck('status')->map->value->unique()->values()->all());
        $this->assertSame(['cancelled'], $order->subOrders->flatMap->items->pluck('fulfillment_status')->map->value->unique()->values()->all());
        $this->assertSame('cancelled', $this->codTransaction($order)->status->value);
        $this->assertStock($this->scenario->vendorListingFbp, 50, 0);
        $this->assertStock($this->scenario->adminListingInventory, (int) $this->scenario->adminListingInventory->quantity_on_hand, 0);
        $this->assertSame(0, Refund::where('order_id', $order->id)->count());
    }

    public function test_admin_master_cancel_is_refused_without_changes_when_a_sub_order_already_shipped(): void
    {
        $order = $this->placeOrder('cod', [$this->scenario->vendorListingFbp, $this->scenario->adminListing]);
        $order->update(['status' => 'partially_shipped']);
        $shipped = $order->subOrders->firstWhere('seller_type', 'vendor');
        $shipped->update(['status' => 'shipped']);

        $this->actingAs($this->admin(), 'admin')
            ->postJson(route('admin.orders.update-status', $order->id), ['new_status' => 'cancelled'])
            ->assertUnprocessable()
            ->assertJsonPath('message', __('admin.orders.status_cancel_blocked'));

        $this->assertSame(0, $order->subOrders()->where('status', 'cancelled')->count());
        $this->assertSame('pending', $this->codTransaction($order)->status->value);
    }

    public function test_admin_cancelling_a_sub_order_by_status_releases_stock_and_rolls_up_the_order(): void
    {
        $order = $this->placeOrder('cod', [$this->scenario->vendorListingFbp]);
        $subOrder = $order->subOrders->first();

        $this->actingAs($this->admin(), 'admin')
            ->postJson(route('admin.orders.update-sub-order-status'), [
                'sub_order_id' => $subOrder->id,
                'new_status' => 'cancelled',
                'reason' => 'vendor unreachable',
            ])
            ->assertOk();

        $this->assertSame('cancelled', $subOrder->fresh()->status->value);
        $this->assertSame('cancelled', $subOrder->items()->first()->fulfillment_status->value);
        $this->assertSame('cancelled', $order->fresh()->status->value);
        $this->assertSame('cancelled', $this->codTransaction($order)->status->value);
        $this->assertStock($this->scenario->vendorListingFbp, 50, 0);
    }

    public function test_force_cancelling_a_shipped_sub_order_closes_its_open_delivery_assignment(): void
    {
        $order = $this->placeOrder('cod', [$this->scenario->vendorListingFbp]);
        $subOrder = $order->subOrders->first();
        $subOrder->update([
            'status' => 'confirmed',
            'carrier_id' => ShippingCarrier::create(['name' => 'Carrier', 'code' => 'c-'.Str::lower(Str::random(6)), 'is_active' => true])->id,
        ]);
        app(ShipmentService::class)->createForSubOrder($subOrder->fresh('items'), 'TRK-'.Str::random(6), CancelActor::Admin, [], true);
        $assignment = DeliveryAssignment::create([
            'shipment_id' => Shipment::where('sub_order_id', $subOrder->id)->value('id'),
            'sub_order_id' => $subOrder->id,
            'agent_id' => $this->scenario->deliveryAgent->id,
            'status' => DeliveryAssignment::STATUS_ACCEPTED,
            'assigned_at' => now(),
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->postJson(route('admin.orders.force-cancel', $order->id), ['reason' => 'lost parcel', 'force' => true])
            ->assertOk();

        $this->assertSame('cancelled', $subOrder->fresh()->status->value);
        $this->assertSame(DeliveryAssignment::STATUS_CANCELLED, $assignment->fresh()->status);
        $this->assertNotNull($assignment->fresh()->cancelled_at);
        $this->assertSame('cancelled', $this->codTransaction($order)->status->value);
    }

    // ── Vendor panel cancel ─────────────────────────────────────────────

    public function test_vendor_cancel_cancels_the_order_and_a_repeat_is_refused_without_double_effects(): void
    {
        $order = $this->placeOrder('cod', [$this->scenario->vendorListingFbp]);
        $subOrder = $order->subOrders->first();
        $this->actAsVendorWith('orders.cancel');

        $this->postJson(route('partner.orders.cancel', $subOrder->sub_order_number), ['reason' => 'out_of_stock'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('cancelled', $subOrder->fresh()->status->value);
        $this->assertSame('cancelled', $order->fresh()->status->value);
        $this->assertSame('cancelled', $this->codTransaction($order)->status->value);
        $this->assertStock($this->scenario->vendorListingFbp, 50, 0);

        $this->postJson(route('partner.orders.cancel', $subOrder->sub_order_number), ['reason' => 'out_of_stock'])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', __('common.exceptions.order.already_cancelled'));

        $this->assertStock($this->scenario->vendorListingFbp, 50, 0);
        Notification::assertSentToTimes($this->scenario->customer, OrderCancelled::class, 1);
    }

    public function test_vendor_cancel_closes_a_sub_order_left_open_after_all_its_items_were_cancelled(): void
    {
        $order = $this->placeOrder('cod', [$this->scenario->vendorListingFbp]);
        $subOrder = $order->subOrders->first();
        // State written by the pre-fix admin item cancel: item cancelled + stock released, statuses untouched.
        app(OrderCancellationService::class)->cancel($subOrder->items, CancelActor::Admin, 'item cancel', true);
        SubOrder::whereKey($subOrder->id)->update(['status' => 'placed', 'cancelled_at' => null]);
        Order::whereKey($order->id)->update(['status' => 'placed', 'cancelled_at' => null]);
        PaymentTransaction::where('order_id', $order->id)->where('gateway', 'cod')->update(['status' => 'pending']);
        $this->actAsVendorWith('orders.cancel');

        $this->postJson(route('partner.orders.cancel', $subOrder->sub_order_number), ['reason' => 'customer_request'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('cancelled', $subOrder->fresh()->status->value);
        $this->assertSame('cancelled', $order->fresh()->status->value);
        $this->assertSame('cancelled', $this->codTransaction($order)->status->value);
        $this->assertStock($this->scenario->vendorListingFbp, 50, 0);
    }

    public function test_vendor_cannot_cancel_a_shipped_sub_order(): void
    {
        $order = $this->placeOrder('cod', [$this->scenario->vendorListingFbp]);
        $subOrder = $order->subOrders->first();
        $subOrder->update(['status' => 'shipped']);
        $this->actAsVendorWith('orders.cancel');

        $this->postJson(route('partner.orders.cancel', $subOrder->sub_order_number), ['reason' => 'out_of_stock'])
            ->assertUnprocessable()
            ->assertJsonPath('message', __('common.exceptions.order.cancel_blocked_shipped'));

        $this->assertSame('shipped', $subOrder->fresh()->status->value);
        $this->assertSame('pending', $this->codTransaction($order)->status->value);
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    /**
     * @param  array<int, VendorListing|AdminListing>  $listings
     */
    private function placeOrder(string $gatewayCode, array $listings): Order
    {
        $this->actingAs($this->scenario->customer, 'customer');
        $cart = app(CartService::class)->getOrCreateCart($this->scenario->customer, $this->scenario->country->id, $this->scenario->country->currency_code);

        foreach ($listings as $listing) {
            CartItem::create([
                'cart_id' => $cart->id,
                $listing instanceof AdminListing ? 'admin_listing_id' : 'vendor_listing_id' => $listing->id,
                'quantity' => 1,
                'unit_price' => (int) $listing->getRawOriginal('price'),
                'added_at' => now(),
            ]);
        }

        $response = $this->postJson("/api/customer/v1/{$this->scenario->country->site_code}/checkout/place-order", [
            'address_id' => $this->scenario->customerAddress->id,
            'country_payment_gateway_id' => $this->scenario->countryPaymentGateways[$gatewayCode]->id,
            'idempotency_key' => (string) Str::uuid(),
        ])->assertCreated();

        $orderNumber = $response->json('data.order.order_number') ?? $response->json('data.order_number');

        return Order::where('order_number', $orderNumber)->with('subOrders.items')->firstOrFail();
    }

    private function codTransaction(Order $order): PaymentTransaction
    {
        return PaymentTransaction::where('order_id', $order->id)->where('gateway', 'cod')->firstOrFail();
    }

    private function admin(): Admin
    {
        foreach (['orders.view', 'vendors.assigned_only'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'admin']);
        }
        $admin = Admin::factory()->create();
        foreach (['orders.view', 'orders.edit', 'orders.cancel'] as $permission) {
            Permission::findOrCreate($permission, 'admin');
        }
        $admin->givePermissionTo(['orders.view', 'orders.edit', 'orders.cancel']);

        return $admin;
    }

    private function actAsVendorWith(string ...$permissions): void
    {
        $vendorAdmin = VendorAdmin::create([
            'vendor_id' => $this->scenario->vendor->id,
            'name' => 'Owner',
            'email' => 'owner-'.Str::random(8).'@example.com',
            'password' => bcrypt('password'),
            'role' => 'owner',
            'is_owner' => true,
            'is_active' => true,
        ]);
        foreach ($permissions as $permission) {
            $vendorAdmin->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'vendor']));
        }
        $this->actingAs($vendorAdmin, 'vendor');
    }
}
