<?php

namespace Tests\Feature;

use App\Jobs\GenerateFbnStorageFeesJob;
use App\Models\Country;
use App\Models\CountryPaymentGateway;
use App\Models\DeliveryAgent;
use App\Models\FbnDailyOverageFee;
use App\Models\FbnStorageFee;
use App\Models\PlatformShippingSubsidy;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use App\Models\ShippingWeightSlab;
use App\Models\ShippingZone;
use App\Models\StorageFeeFreePeriodRule;
use App\Models\Vendor;
use App\Models\VendorListing;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Services\ExceptionalZoneService;
use App\Services\FbnDailyOverageFeeService;
use App\Services\ShippingFeeCalculator;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Shipping / delivery / fulfilment / gateway-fee money accepts decimals (DECIMAL(19,4)):
 * a 4.75 base fee, a 1.9 per-kg rate, a 0.5 slab fee, a 1.9 storage rate, a 2.35 COD net.
 */
class DecimalShippingDeliveryMoneyTest extends TestCase
{
    use RefreshDatabase;

    private Country $country;

    protected function setUp(): void
    {
        parent::setUp();

        $this->country = Country::create([
            'id' => (string) Str::uuid(),
            'iso_code_2' => 'AE',
            'iso_code_3' => 'ARE',
            'name_ar' => 'الإمارات',
            'name_en' => 'UAE - '.Str::random(6),
            'currency_code' => 'AED',
            'vat_rate' => 5.00,
            'is_active' => true,
            'is_launched' => true,
            'cod_available' => true,
            'timezone' => 'Asia/Dubai',
        ]);
    }

    private function lane(array $rate): array
    {
        $zone = ShippingZone::create(['name' => 'Zone '.Str::random(5), 'country_id' => $this->country->id]);
        $method = ShippingMethod::create(['name' => 'Std', 'code' => 'std-'.Str::lower(Str::random(6))]);
        $shippingRate = ShippingRate::create(array_merge([
            'destination_zone_id' => $zone->id,
            'shipping_method_id' => $method->id,
            'min_weight_grams' => 1000,
            'volumetric_divisor' => 5000,
            'is_active' => true,
        ], $rate));

        return [$zone, $method, $shippingRate];
    }

    private function calculate(ShippingZone $zone, ShippingMethod $method, int $weightGrams, string $payment = 'card', int|float $subtotal = 100): ?array
    {
        $listing = new VendorListing([
            'declared_weight_grams' => $weightGrams,
            'vendor_covers_delivery' => false,
        ]);
        $listing->id = (string) Str::uuid();

        return app(ShippingFeeCalculator::class)->calculate([
            'listing' => $listing,
            'destination_zone_id' => $zone->id,
            'shipping_method_id' => $method->id,
            'payment_method' => $payment,
            'destination_city_id' => null,
            'country_id' => $this->country->id,
            'order_subtotal' => $subtotal,
        ]);
    }

    public function test_decimal_shipping_rate_with_per_kg_and_weight_slab(): void
    {
        [$zone, $method] = $this->lane([
            'base_fee' => 4.75,
            'rate_per_kg' => 1.9,
            'carrier_rate' => 4.75,
            'cod_extra_fee' => 1.25,
        ]);
        ShippingWeightSlab::create([
            'shipping_method_id' => $method->id,
            'country_id' => $this->country->id,
            'min_weight_grams' => 2000,
            'max_weight_grams' => null,
            'extra_fee' => 0.5,
            'is_active' => true,
        ]);

        // 2500g: 1500g over the 1000g minimum -> 1.5kg * 1.9 = 2.85; + slab 0.5; + base 4.75 = 8.10
        $result = $this->calculate($zone, $method, 2500, 'cod');

        $this->assertEquals(8.1, $result['fee']);
        $this->assertEquals(1.25, $result['cod_fee']);
        $this->assertEquals(0, $result['shipping_gap']);

        // Under the slab and minimum weight: just the 4.75 base fee.
        $this->assertEquals(4.75, $this->calculate($zone, $method, 800)['fee']);
    }

    public function test_decimal_exceptional_gap_is_split_without_leakage(): void
    {
        [$zone, $method] = $this->lane([
            'base_fee' => 4.75,
            'rate_per_kg' => 0,
            'carrier_rate' => 10.5,
        ]);
        PlatformShippingSubsidy::create([
            'shipping_zone_id' => $zone->id,
            'shipping_method_id' => $method->id,
            'currency' => 'AED',
            'split_type' => 'percentage',
            'vendor_share_pct' => 50,
            'subsidy_cap' => 0,
            'is_active' => true,
        ]);

        $result = $this->calculate($zone, $method, 500);

        $this->assertTrue($result['is_exceptional']);
        $this->assertEquals(5.75, $result['shipping_gap']);
        $this->assertEquals(2.88, $result['vendor_contribution']);
        $this->assertEquals(2.87, $result['admin_subsidy_amount']);
        $this->assertEquals(5.75, round($result['vendor_contribution'] + $result['admin_subsidy_amount'], 2));
    }

    public function test_exceptional_zone_service_keeps_decimal_gap(): void
    {
        // Pure arithmetic path: no alert -> null, proving a float normal fee is accepted by the signature.
        $listing = new VendorListing(['warehouse_id' => null]);
        $city = new \App\Models\City;

        $this->assertNull(app(ExceptionalZoneService::class)->resolve($listing, $city, 4.75));
    }

    public function test_decimal_storage_rate_generates_decimal_fbn_fee(): void
    {
        $vendor = Vendor::create([
            'name' => 'Test Vendor',
            'email' => 'vendor-'.Str::lower(Str::random(8)).'@example.test',
            'phone' => '+9715'.fake()->numerify('########'),
            'password' => bcrypt('password'),
            'store_name' => 'Test Store '.Str::random(6),
            'store_slug' => 'test-store-'.Str::lower(Str::random(8)),
            'business_type' => 'llc',
            'payout_schedule' => 'monthly',
            'global_status' => 'active',
            'country_id' => $this->country->id,
            'approved_at' => now(),
            'warranty_months' => 12,
        ]);

        $warehouse = Warehouse::create([
            'country_id' => $this->country->id,
            'name' => 'Platform Warehouse',
            'code' => 'PW-'.Str::upper(Str::random(6)),
            'type' => 'platform_fbn',
            'is_active' => true,
            'storage_rate_per_m3_price' => 1.9,
            'storage_currency' => 'AED',
            'daily_fee_per_unit' => 0.35,
            'free_storage_days' => 0,
        ]);
        $this->assertEquals(1.9, $warehouse->fresh()->storage_rate_per_m3_price);

        StorageFeeFreePeriodRule::query()->delete();
        StorageFeeFreePeriodRule::create(['min_weight_grams' => 0, 'max_weight_grams' => null, 'free_days' => 1]);

        $listing = VendorListing::create([
            'id' => (string) Str::uuid(),
            'vendor_id' => $vendor->id,
            'product_variant_id' => ProductVariant::factory()->create()->id,
            'country_id' => $this->country->id,
            'warehouse_id' => $warehouse->id,
            'price' => 1000.00,
            'currency' => 'AED',
            'condition' => 'new',
            'fulfillment_model' => 'fbn',
            'status' => 'active',
            'declared_weight_grams' => 1000,
            'declared_length_cm' => 100,
            'declared_width_cm' => 100,
            'declared_height_cm' => 100,
        ]);
        $inventory = WarehouseInventory::create([
            'vendor_listing_id' => $listing->id,
            'warehouse_id' => $warehouse->id,
            'quantity_on_hand' => 3,
            'quantity_reserved' => 0,
        ]);
        DB::table('warehouse_inventories')->where('id', $inventory->id)->update(['first_stocked_at' => now()->subDays(40)]);

        GenerateFbnStorageFeesJob::dispatchSync(now()->format('Y-m'));

        // 1 m3 per unit * 1.9 per m3 * 3 units = 5.70 (the old integer cast would have given 2 -> 6).
        $fee = FbnStorageFee::where('vendor_id', $vendor->id)->firstOrFail();
        $this->assertEquals(1.9, $fee->rate_per_unit);
        $this->assertEquals(5.7, $fee->total_fee);

        // Daily overage: 3 units * 0.35 = 1.05
        DB::table('inventory_movements')->insert([
            'id' => (string) Str::uuid(),
            'warehouse_inventory_id' => $inventory->id,
            'movement_type' => 'inbound',
            'quantity_delta' => 3,
            'quantity_after' => 3,
            'created_at' => now()->subDays(40),
        ]);
        app(FbnDailyOverageFeeService::class)->computeOverageForDate(Carbon::today());
        $overage = FbnDailyOverageFee::where('vendor_id', $vendor->id)->firstOrFail();
        $this->assertEquals(0.35, $overage->fee_per_unit);
        $this->assertEquals(1.05, $overage->total_fee);
    }

    public function test_gateway_fixed_fee_accepts_decimals(): void
    {
        $gatewayId = (string) Str::uuid();
        DB::table('payment_gateways')->insert([
            'id' => $gatewayId,
            'code' => 'gw-'.Str::lower(Str::random(6)),
            'name' => 'Test GW',
            'type' => 'offline',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cpg = CountryPaymentGateway::create([
            'country_id' => $this->country->id,
            'gateway_id' => $gatewayId,
            'display_name_en' => 'Test GW',
            'environment' => 'sandbox',
            'fee_pct' => 2.5,
            'fee_fixed' => 1.75,
            'min_order' => 0.5,
            'is_active' => true,
        ]);

        $fresh = $cpg->fresh();
        $this->assertEquals(1.75, $fresh->fee_fixed);
        $this->assertEquals(0.5, $fresh->min_order);
    }

    public function test_agent_cod_settlement_net_keeps_decimals(): void
    {
        $agent = DeliveryAgent::create([
            'country_id' => $this->country->id,
            'name' => 'Agent',
            'email' => 'agent-'.Str::lower(Str::random(8)).'@example.test',
            'phone' => '+9715'.fake()->numerify('########'),
            'password' => bcrypt('password'),
            'status' => 'active',
            'agent_type' => 'third_party',
            'vehicle_type' => 'motorcycle',
        ]);

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        $orderId = (string) Str::uuid();
        $subOrderId = (string) Str::uuid();
        DB::table('orders')->insert([
            'id' => $orderId,
            'order_number' => 'T'.Str::upper(Str::random(8)),
            'customer_id' => (string) Str::uuid(),
            'status' => 'delivered',
            'currency' => 'AED',
            'subtotal' => 100, 'shipping' => 0, 'tax' => 0, 'total' => 100.5,
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'shipping_address_snapshot' => '{}',
            'ip_address' => '127.0.0.1',
            'placed_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('sub_orders')->insert([
            'id' => $subOrderId,
            'order_id' => $orderId,
            'sub_order_number' => 'S'.Str::upper(Str::random(8)),
            'seller_type' => 'vendor',
            'status' => 'delivered',
            'fulfillment_model' => 'fbm',
            'subtotal' => 100, 'shipping' => 0, 'tax' => 0, 'platform_commission' => 0, 'vendor_payout' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $assignmentId = (string) Str::uuid();
        DB::table('delivery_assignments')->insert([
            'id' => $assignmentId,
            'shipment_id' => (string) Str::uuid(),
            'sub_order_id' => $subOrderId,
            'agent_id' => $agent->id,
            'status' => 'delivered',
            'assigned_at' => now(),
            'delivered_at' => now(),
            'cod_amount_collected' => 100.5,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('delivery_agent_earnings')->insert([
            'id' => (string) Str::uuid(),
            'agent_id' => $agent->id,
            'delivery_assignment_id' => $assignmentId,
            'order_id' => $orderId,
            'earning_type' => 'base_fee',
            'amount' => 98.15,
            'currency' => 'AED',
            'status' => 'approved',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // The agent owes the platform 2.35, taken from the agent wallet: fund it first.
        $walletService = app(WalletService::class);
        $walletService->credit($walletService->getOrCreateWallet('delivery_agent', $agent->id, 'AED'), 10, 'test', null, 'seed');

        $settlement = $walletService->settleAgentCod($agent, today()->toDateString(), today()->toDateString());

        $this->assertEquals(100.5, $settlement->total_cod_collected);
        $this->assertEquals(98.15, $settlement->total_earnings_owed);
        $this->assertEquals(2.35, $settlement->net_to_remit);
        $this->assertEquals(2.35, $settlement->fresh()->net_to_remit);
        $this->assertEquals(7.65, $walletService->getOrCreateWallet('delivery_agent', $agent->id, 'AED')->fresh()->balance);
    }
}
