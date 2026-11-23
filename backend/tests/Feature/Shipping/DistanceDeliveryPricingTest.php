<?php

namespace Tests\Feature\Shipping;

use App\Enums\GlobalSystemType;
use App\Models\Address;
use App\Models\Admin;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\ShippingDistancePricing;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use App\Models\VendorListing;
use App\Services\Customer\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * Client item #19: free-delivery radius + distance tiers per zone/method, through the cart and checkout APIs.
 */
class DistanceDeliveryPricingTest extends TestCase
{
    use RefreshDatabase;

    private const STORE = [23.5880, 58.3829];

    private const KM_PER_DEGREE_LAT = 111.195;

    private MarketplaceScenario $scenario;

    private ShippingMethod $method;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scenario = MarketplaceScenario::make()->build();
        $this->scenario->country->update(['site_code' => 'ae-'.Str::lower(Str::random(6))]);
        $this->scenario->vendorWarehouse->update(['latitude' => self::STORE[0], 'longitude' => self::STORE[1]]);
        $this->scenario->platformWarehouse->update(['latitude' => self::STORE[0], 'longitude' => self::STORE[1]]);
        $this->scenario->vendorListingFbp->update(['global_system_type' => GlobalSystemType::MerchantFbp]);
        $this->scenario->vendorListingFbn->update(['global_system_type' => GlobalSystemType::ExpressFbn]);

        $this->method = ShippingMethod::create(['name' => 'Quick', 'code' => 'quick-'.Str::lower(Str::random(6)), 'is_active' => true]);

        ShippingRate::create([
            'destination_zone_id' => $this->scenario->shippingZone->id,
            'shipping_method_id' => $this->method->id,
            'base_fee' => 2,
            'carrier_rate' => 0,
            'carrier_rate_per_kg' => 0,
            'rate_per_kg' => 0,
            'free_shipping_threshold' => 5000,
            'is_active' => true,
        ]);
    }

    /** Free within 3 km, 3–6 km → 1.5, 6 km and above → 2.5, not offered beyond 10 km. */
    private function distanceRule(array $categoryIds = []): ShippingDistancePricing
    {
        $pricing = ShippingDistancePricing::create([
            'shipping_zone_id' => $this->scenario->shippingZone->id,
            'shipping_method_id' => $this->method->id,
            'free_radius_km' => 3,
            'max_service_radius_km' => 10,
            'is_active' => true,
        ]);
        $pricing->tiers()->createMany([
            ['from_km' => 3, 'to_km' => 6, 'price' => 1.5],
            ['from_km' => 6, 'to_km' => null, 'price' => 2.5],
        ]);
        $pricing->categories()->sync($categoryIds);

        return $pricing;
    }

    private function customerAt(?float $kmNorthOfStore): void
    {
        $this->scenario->customerAddress->update([
            'latitude' => $kmNorthOfStore === null ? null : self::STORE[0] + $kmNorthOfStore / self::KM_PER_DEGREE_LAT,
            'longitude' => $kmNorthOfStore === null ? null : self::STORE[1],
        ]);
    }

    private function addToCart(VendorListing $listing, int $quantity = 1): void
    {
        $cart = app(CartService::class)->getOrCreateCart($this->scenario->customer, $this->scenario->country->id, $this->scenario->country->currency_code);

        CartItem::create([
            'cart_id' => $cart->id,
            'vendor_listing_id' => $listing->id,
            'quantity' => $quantity,
            'unit_price' => (int) $listing->getRawOriginal('price'),
            'selected_shipping_method_id' => $this->method->id,
            'added_at' => now(),
        ]);
    }

    private function prepare(): TestResponse
    {
        return $this->actingAs($this->scenario->customer, 'customer')
            ->postJson("/api/customer/v1/{$this->scenario->country->site_code}/checkout/prepare", [
                'address_id' => $this->scenario->customerAddress->id,
                'country_payment_gateway_id' => $this->scenario->countryPaymentGateways['cod']->id,
            ]);
    }

    public function test_cart_and_checkout_charge_the_distance_tier_for_the_store_leg(): void
    {
        $this->distanceRule();
        $this->customerAt(4.2);
        $this->addToCart($this->scenario->vendorListingFbp);

        $cart = $this->actingAs($this->scenario->customer, 'customer')
            ->getJson("/api/customer/v1/{$this->scenario->country->site_code}/cart")
            ->assertOk();
        $cart->assertJsonPath('data.shipping_groups.0.distance_pricing.status', 'tier')
            ->assertJsonPath('data.shipping_groups.0.distance_pricing.distance_km', 4.2)
            ->assertJsonPath('data.shipping_groups.0.distance_pricing.fee', 1.5)
            ->assertJsonPath('data.shipping_groups.0.distance_pricing.free_radius_km', 3);

        $prepare = $this->prepare()->assertOk();
        $this->assertEquals(1.5, $prepare->json('data.shipping.total_fee'));
        $this->assertSame('tier', $prepare->json('data.shipping.groups.0.distance.status'));
    }

    public function test_fbn_lines_are_priced_from_the_fulfilling_warehouse_instead_of_shipping_free(): void
    {
        $this->distanceRule();
        $this->customerAt(7.0);
        $this->addToCart($this->scenario->vendorListingFbn);

        $this->assertEquals(2.5, $this->prepare()->assertOk()->json('data.shipping.total_fee'));
    }

    public function test_free_radius_and_order_amount_threshold_make_delivery_free(): void
    {
        $this->distanceRule();
        $this->customerAt(2.5);
        $this->addToCart($this->scenario->vendorListingFbp);

        $inRadius = $this->prepare()->assertOk();
        $this->assertEquals(0, $inRadius->json('data.shipping.total_fee'));
        $this->assertSame('free_within_radius', $inRadius->json('data.shipping.groups.0.distance.status'));

        // 5 × 1000 reaches the zone rate's 5000 free-shipping threshold at a distance that would cost 2.5.
        $this->customerAt(8.0);
        CartItem::query()->update(['quantity' => 5]);

        $byAmount = $this->prepare()->assertOk();
        $this->assertEquals(0, $byAmount->json('data.shipping.total_fee'));
        $this->assertSame('free_by_order_amount', $byAmount->json('data.shipping.groups.0.distance.status'));
    }

    public function test_beyond_the_max_radius_the_method_is_unavailable_with_a_reason(): void
    {
        $this->distanceRule();
        $this->customerAt(12.0);
        $this->addToCart($this->scenario->vendorListingFbp);

        $methods = $this->actingAs($this->scenario->customer, 'customer')
            ->getJson("/api/customer/v1/{$this->scenario->country->site_code}/checkout/shipping-methods?address_id={$this->scenario->customerAddress->id}")
            ->assertOk();
        $quick = collect($methods->json('data.shipping_methods'))->firstWhere('id', $this->method->id);
        $this->assertFalse($quick['is_available']);
        $this->assertStringContainsString('10.0', $quick['unavailable_reason']);

        $this->prepare()
            ->assertUnprocessable()
            ->assertJsonPath('message', __('distance_delivery.method_unavailable', [
                'method' => 'Quick',
                'reason' => __('distance_delivery.unavailable', ['distance' => '12.0', 'max' => '10.0']),
            ]));
    }

    public function test_missing_customer_coordinates_keep_the_normal_zone_rate(): void
    {
        $this->distanceRule();
        $this->customerAt(null);
        $this->addToCart($this->scenario->vendorListingFbp);

        $prepare = $this->prepare()->assertOk();
        $this->assertEquals(2, $prepare->json('data.shipping.total_fee'));
        $this->assertSame('fallback', $prepare->json('data.shipping.groups.0.distance.status'));
        $this->assertSame('missing_coordinates', $prepare->json('data.shipping.groups.0.distance.fallback_reason'));
    }

    public function test_a_rule_scoped_to_other_categories_does_not_apply(): void
    {
        $grocery = Category::factory()->create();
        $this->distanceRule([$grocery->id]);
        $this->customerAt(4.2);
        $this->addToCart($this->scenario->vendorListingFbp);

        $prepare = $this->prepare()->assertOk();
        $this->assertEquals(2, $prepare->json('data.shipping.total_fee'));
        $this->assertNull($prepare->json('data.shipping.groups.0.distance'));
    }

    public function test_admin_saves_tiers_and_overlaps_are_rejected(): void
    {
        foreach (['settings.view', 'settings.edit', 'vendors.assigned_only'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'admin']);
        }
        $editor = Admin::factory()->create();
        $editor->givePermissionTo(['settings.view', 'settings.edit']);
        $url = route('admin.shipping-zones.distance-pricing.update', [$this->scenario->shippingZone, $this->method]);

        $this->actingAs($editor, 'admin')->putJson($url, [
            'is_active' => true,
            'free_radius_km' => '٣',
            'max_service_radius_km' => '10',
            'tiers' => [['from_km' => '3', 'to_km' => '6', 'price' => '1.5'], ['from_km' => '5', 'to_km' => '', 'price' => '2.5']],
        ])->assertUnprocessable()->assertJsonValidationErrors('tiers');
        $this->assertDatabaseCount('shipping_distance_pricings', 0);

        $this->actingAs($editor, 'admin')->putJson($url, [
            'is_active' => true,
            'free_radius_km' => '٣',
            'max_service_radius_km' => '10',
            'tiers' => [['from_km' => '3', 'to_km' => '6', 'price' => '1.5'], ['from_km' => '7', 'to_km' => '', 'price' => '2.5']],
        ])->assertOk()
            ->assertJsonPath('data.rule.free_radius_km', 3)
            ->assertJsonCount(2, 'data.rule.tiers')
            ->assertJsonPath('data.warnings.0', __('distance_delivery.admin.warnings.tier_gap', ['from' => 6, 'to' => 7]));

        $viewer = Admin::factory()->create();
        $viewer->givePermissionTo('settings.view');
        $this->actingAs($viewer, 'admin')->putJson($url, ['tiers' => []])->assertForbidden();
        $this->actingAs($viewer, 'admin')
            ->getJson(route('admin.shipping-zones.distance-pricing.index', $this->scenario->shippingZone))
            ->assertOk()
            ->assertJsonPath("data.rules.{$this->method->id}.max_service_radius_km", 10);
    }

    public function test_placed_order_snapshots_the_distance_decision_from_the_store_location(): void
    {
        $pricing = $this->distanceRule();
        // No warehouse coordinates: the vendor's store location (business address pin) is the origin.
        $this->scenario->vendorWarehouse->update(['latitude' => null, 'longitude' => null]);
        $storeAddress = Address::create([
            'addressable_type' => $this->scenario->vendor::class,
            'addressable_id' => $this->scenario->vendor->id,
            'street_address' => 'Store',
            'latitude' => self::STORE[0],
            'longitude' => self::STORE[1],
            'address_type' => 'both',
        ]);
        $this->scenario->vendor->forceFill(['business_address_id' => $storeAddress->id])->save();
        $this->customerAt(4.2);
        $this->addToCart($this->scenario->vendorListingFbp);

        $response = $this->actingAs($this->scenario->customer, 'customer')
            ->postJson("/api/customer/v1/{$this->scenario->country->site_code}/checkout/place-order", [
                'address_id' => $this->scenario->customerAddress->id,
                'country_payment_gateway_id' => $this->scenario->countryPaymentGateways['cod']->id,
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertCreated();

        $orderNumber = $response->json('data.order.order_number') ?? $response->json('data.order_number');
        $subOrder = Order::where('order_number', $orderNumber)->firstOrFail()->subOrders()->sole();

        $this->assertEquals(1.5, $subOrder->shipping);
        $this->assertSame($pricing->id, $subOrder->distance_pricing['rule_id']);
        $this->assertSame('tier', $subOrder->distance_pricing['status']);
        $this->assertTrue($subOrder->distance_pricing['applied']);
        $this->assertEquals(4.2, $subOrder->distance_pricing['distance_km']);
        $this->assertEquals(1.5, $subOrder->distance_pricing['fee']);
        $this->assertEquals(['from_km' => 3, 'to_km' => 6, 'price' => 1.5], $subOrder->distance_pricing['tier']);
        $this->assertSame('store_location', $subOrder->distance_pricing['origin_type']);
        $this->assertSame($this->scenario->vendor->id, $subOrder->distance_pricing['origin_id']);

        $this->assertArrayNotHasKey('snapshot', (array) $this->prepare()->json('data.shipping.groups.0.distance'));

        Permission::firstOrCreate(['name' => 'orders.view', 'guard_name' => 'admin']);
        Permission::firstOrCreate(['name' => 'vendors.assigned_only', 'guard_name' => 'admin']);
        $opsAdmin = Admin::factory()->create();
        $opsAdmin->givePermissionTo('orders.view');
        $this->actingAs($opsAdmin, 'admin')
            ->get(route('admin.orders.show', $subOrder->order_id))
            ->assertOk()
            ->assertSee('data-distance-snapshot', false)
            ->assertSee(__('store_location.order.origin_types.store_location'));
    }

    public function test_distance_tab_lists_stores_and_warehouses_without_coordinates(): void
    {
        foreach (['settings.view', 'vendors.assigned_only'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'admin']);
        }
        $viewer = Admin::factory()->create();
        $viewer->givePermissionTo('settings.view');
        $url = route('admin.shipping-zones.distance-pricing.index', $this->scenario->shippingZone);
        $vendorNames = fn ($response) => collect($response->json('data.coverage.vendors'))->pluck('name');

        $this->scenario->vendorWarehouse->update(['latitude' => null, 'longitude' => null]);
        $missing = $this->actingAs($viewer, 'admin')->getJson($url)->assertOk();
        $this->assertContains($this->scenario->vendor->store_name, $vendorNames($missing));
        $this->assertContains($this->scenario->vendorWarehouse->name, collect($missing->json('data.coverage.warehouses'))->pluck('name'));

        $this->scenario->vendorWarehouse->update(['latitude' => self::STORE[0], 'longitude' => self::STORE[1]]);
        $fixed = $this->actingAs($viewer, 'admin')->getJson($url)->assertOk();
        $this->assertNotContains($this->scenario->vendor->store_name, $vendorNames($fixed));
    }
}
