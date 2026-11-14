<?php

namespace Tests\Feature\Partner;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\ShippingCarrier;
use App\Models\ShippingMethod;
use App\Models\SubOrder;
use App\Models\VendorAdmin;
use App\Services\Customer\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * The vendor partner panel fulfils its own sub-orders: "ship" right after "confirm" (the panel has no
 * processing/packed buttons), and "cancel" before shipping through the single cancellation engine.
 */
class PartnerOrderFulfillmentTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $scenario;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->scenario = MarketplaceScenario::make()->build();
        $this->scenario->country->update(['site_code' => 'om-'.Str::lower(Str::random(6))]);
    }

    private function placedVendorSubOrder(bool $withCarrier = true): SubOrder
    {
        $this->actingAs($this->scenario->customer, 'customer');
        $cart = app(CartService::class)->getOrCreateCart($this->scenario->customer, $this->scenario->country->id, $this->scenario->country->currency_code);
        CartItem::create([
            'cart_id' => $cart->id,
            'vendor_listing_id' => $this->scenario->vendorListingFbp->id,
            'quantity' => 1,
            'unit_price' => (int) $this->scenario->vendorListingFbp->getRawOriginal('price'),
            'added_at' => now(),
        ]);

        $orderNumber = $this->postJson("/api/customer/v1/{$this->scenario->country->site_code}/checkout/place-order", [
            'address_id' => $this->scenario->customerAddress->id,
            'country_payment_gateway_id' => $this->scenario->countryPaymentGateways['wallet']->id,
            'idempotency_key' => (string) Str::uuid(),
        ])->assertCreated()->json('data.order_number');

        $subOrder = Order::where('order_number', $orderNumber)->firstOrFail()->subOrders()->where('seller_type', 'vendor')->firstOrFail();
        $subOrder->update([
            'shipping_method_id' => ShippingMethod::create(['name' => 'Std', 'code' => 'std-'.Str::lower(Str::random(6))])->id,
            'carrier_id' => $withCarrier ? ShippingCarrier::create(['name' => 'Carrier', 'code' => 'c-'.Str::lower(Str::random(6)), 'is_active' => true])->id : null,
        ]);

        return $subOrder->fresh();
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

    public function test_vendor_can_ship_a_sub_order_it_just_confirmed(): void
    {
        $subOrder = $this->placedVendorSubOrder();
        $this->actAsVendorWith('orders.process');

        $this->postJson(route('partner.orders.confirm', $subOrder->sub_order_number))->assertOk();
        $this->postJson(route('partner.orders.ship', $subOrder->sub_order_number), ['tracking_number' => 'TRK-1'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('shipped', $subOrder->fresh()->status->value);
    }

    public function test_vendor_driven_delivery_shows_tracking_events_to_the_customer(): void
    {
        $subOrder = $this->placedVendorSubOrder();
        $this->actAsVendorWith('orders.process');

        $this->postJson(route('partner.orders.confirm', $subOrder->sub_order_number))->assertOk();
        $this->postJson(route('partner.orders.ship', $subOrder->sub_order_number), ['tracking_number' => 'TRK-1'])->assertOk();
        $this->postJson(route('partner.orders.out-for-delivery', $subOrder->sub_order_number))->assertOk();
        $this->postJson(route('partner.orders.deliver', $subOrder->sub_order_number))->assertOk();

        $this->actingAs($this->scenario->customer, 'customer');
        $response = $this->postJson("/api/customer/v1/{$this->scenario->country->site_code}/sub-orders/{$subOrder->sub_order_number}/track")
            ->assertOk();

        $this->assertSame(
            ['label_created', 'out_for_delivery', 'delivered'],
            array_column($response->json('data.tracking.events'), 'status'),
        );
        $this->assertSame('delivered', $subOrder->shipments()->first()->status->value);
    }

    public function test_shipping_without_an_assigned_carrier_is_refused_cleanly(): void
    {
        $subOrder = $this->placedVendorSubOrder(withCarrier: false);
        $this->actAsVendorWith('orders.process');

        $this->postJson(route('partner.orders.ship', $subOrder->sub_order_number), ['tracking_number' => 'TRK-1'])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertSame('placed', $subOrder->fresh()->status->value);
    }

    public function test_vendor_cancel_releases_the_reserved_stock_and_cancels_the_order(): void
    {
        $subOrder = $this->placedVendorSubOrder();
        $inventory = $this->scenario->vendorListingFbpInventory;
        $reservedBefore = $inventory->fresh()->quantity_reserved;
        $this->actAsVendorWith('orders.cancel');

        $this->postJson(route('partner.orders.cancel', $subOrder->sub_order_number), ['reason' => 'out_of_stock'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('cancelled', $subOrder->fresh()->status->value);
        $this->assertSame('cancelled', $subOrder->order->fresh()->status->value);
        $this->assertSame($reservedBefore - 1, $inventory->fresh()->quantity_reserved);
    }
}
