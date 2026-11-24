<?php

namespace Tests\Feature\Orders;

use App\Enums\CancelActor;
use App\Models\Admin;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\SubOrder;
use App\Models\VendorAdmin;
use App\Services\Customer\CartService;
use App\Services\OrderCancellationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * R3 (client retest 2026-11-24): after a full cancel of a COD order the admin "COD remittance" box and
 * the partner order alert still read "pending — cash with courier", because both only looked at
 * sub_orders.cod_remittance_confirmed (false forever for an order that was never delivered).
 */
class CancelledCodCollectionStatusTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $scenario;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        $this->scenario = MarketplaceScenario::make()->build();
        $this->scenario->country->update(['site_code' => 'ae-'.Str::lower(Str::random(6))]);

        $this->actingAs($this->scenario->customer, 'customer');
        $cart = app(CartService::class)->getOrCreateCart($this->scenario->customer, $this->scenario->country->id, $this->scenario->country->currency_code);
        CartItem::create([
            'cart_id' => $cart->id,
            'vendor_listing_id' => $this->scenario->vendorListingFbp->id,
            'quantity' => 1,
            'unit_price' => (int) $this->scenario->vendorListingFbp->getRawOriginal('price'),
            'added_at' => now(),
        ]);
        $number = $this->postJson("/api/customer/v1/{$this->scenario->country->site_code}/checkout/place-order", [
            'address_id' => $this->scenario->customerAddress->id,
            'country_payment_gateway_id' => $this->scenario->countryPaymentGateways['cod']->id,
            'idempotency_key' => (string) Str::uuid(),
        ])->assertCreated()->json('data.order_number');

        $this->order = Order::where('order_number', $number)->firstOrFail();
    }

    private function vendorSubOrder(): SubOrder
    {
        return $this->order->subOrders()->where('seller_type', 'vendor')->firstOrFail();
    }

    private function adminWithOrdersView(): Admin
    {
        foreach (['orders.view', 'vendors.assigned_only'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'admin']);
        }
        $admin = Admin::factory()->create();
        $admin->givePermissionTo('orders.view');

        return $admin;
    }

    private function vendorAdmin(): VendorAdmin
    {
        $vendorAdmin = VendorAdmin::create([
            'vendor_id' => $this->scenario->vendor->id,
            'name' => 'Vendor Owner',
            'email' => 'vendor-owner-'.Str::random(8).'@example.com',
            'password' => bcrypt('password'),
            'role' => 'owner',
            'is_owner' => true,
            'is_active' => true,
        ]);
        foreach (['orders.view', 'orders.process'] as $permission) {
            $vendorAdmin->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'vendor']));
        }

        return $vendorAdmin;
    }

    public function test_an_open_cod_order_still_shows_cash_pending_with_the_courier(): void
    {
        $this->assertSame(SubOrder::COD_COLLECTION_PENDING, $this->vendorSubOrder()->codCollectionStatus());

        $this->actingAs($this->adminWithOrdersView(), 'admin')
            ->get(route('admin.orders.show', $this->order->id))
            ->assertOk()
            ->assertSee(__('admin.orders.cod_pending'))
            ->assertDontSee(__('admin.orders.cod_cancelled_nothing_to_collect'));
    }

    public function test_a_fully_cancelled_cod_order_shows_nothing_to_collect_to_admin_and_partner(): void
    {
        app(OrderCancellationService::class)->cancelOrder($this->order, CancelActor::Admin, 'customer unreachable');

        $subOrder = $this->vendorSubOrder();
        $this->assertSame(SubOrder::COD_COLLECTION_CANCELLED, $subOrder->codCollectionStatus());

        $this->actingAs($this->adminWithOrdersView(), 'admin')
            ->get(route('admin.orders.show', $this->order->id))
            ->assertOk()
            ->assertSee(__('admin.orders.cod_cancelled_nothing_to_collect'))
            ->assertDontSee(__('admin.orders.cod_pending'));

        $this->actingAs($this->vendorAdmin(), 'vendor')
            ->get(route('partner.orders.show', $subOrder->sub_order_number))
            ->assertOk()
            ->assertSee(__('partner.orders.cod_cancelled_nothing_to_collect'))
            ->assertDontSee(__('partner.orders.cod_pending_notice'));
    }

    public function test_the_arabic_admin_box_reads_cancelled_for_a_cancelled_cod_order(): void
    {
        app(OrderCancellationService::class)->cancelOrder($this->order, CancelActor::Admin, 'customer unreachable');
        app()->setLocale('ar');

        $this->actingAs($this->adminWithOrdersView(), 'admin')
            ->get(route('admin.orders.show', $this->order->id))
            ->assertOk()
            ->assertSee(trans('admin.orders.cod_cancelled_nothing_to_collect', [], 'ar'));
    }
}
