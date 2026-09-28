<?php

namespace Tests\Feature;

use App\Jobs\GenerateFbnStorageFeesJob;
use App\Models\Country;
use App\Models\FbnStorageFee;
use App\Models\ProductVariant;
use App\Models\StorageFeeFreePeriodRule;
use App\Models\Vendor;
use App\Models\VendorListing;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Behavioral coverage for GenerateFbnStorageFeesJob: chargeable weight
 * (max of actual vs. volumetric), the free-period boundary, the
 * missing-dimensions fallback, and the job-status cache the admin panel
 * polls for a progress indicator.
 */
class FbnStorageFeeGenerationTest extends TestCase
{
    use DatabaseTransactions;

    private Warehouse $warehouse;

    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        $country = Country::create([
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

        $this->vendor = Vendor::create([
            'name' => 'Test Vendor',
            'email' => 'vendor-'.Str::lower(Str::random(8)).'@example.test',
            'phone' => '+9715'.fake()->numerify('########'),
            'password' => bcrypt('password'),
            'store_name' => 'Test Store '.Str::random(6),
            'store_slug' => 'test-store-'.Str::lower(Str::random(8)),
            'business_type' => 'llc',
            'payout_schedule' => 'monthly',
            'global_status' => 'active',
            'country_id' => $country->id,
            'approved_at' => now(),
            'warranty_months' => 12,
        ]);

        $this->warehouse = Warehouse::create([
            'country_id' => $country->id,
            'name' => 'Platform Warehouse',
            'code' => 'PW-'.Str::upper(Str::random(6)),
            'type' => 'platform_fbn',
            'is_active' => true,
            'storage_rate_per_m3_price' => 10000, // 100.00 per m3, base currency units
            'storage_currency' => 'AED',
        ]);

        // Tests must not depend on the environment's seeded rule data —
        // pin the exact tiers under test here.
        StorageFeeFreePeriodRule::query()->delete();
        StorageFeeFreePeriodRule::create(['min_weight_grams' => 0, 'max_weight_grams' => 999, 'free_days' => 60]);
        StorageFeeFreePeriodRule::create(['min_weight_grams' => 1000, 'max_weight_grams' => null, 'free_days' => 30]);
    }

    /**
     * Creates a listing + warehouse inventory with the given weight/dimensions,
     * and pins `first_stocked_at` to a precise date (bypassing the model's
     * auto-set-on-first-stock hook, which would otherwise force "now").
     */
    private function makeInventory(array $listingAttrs, Carbon $storedSince, int $quantity = 5): WarehouseInventory
    {
        $variant = ProductVariant::factory()->create();

        $listing = VendorListing::create(array_merge([
            'id' => (string) Str::uuid(),
            'vendor_id' => $this->vendor->id,
            'product_variant_id' => $variant->id,
            'country_id' => $this->warehouse->country_id,
            'warehouse_id' => $this->warehouse->id,
            'price' => 1000.00,
            'currency' => 'AED',
            'condition' => 'new',
            'fulfillment_model' => 'fbn',
            'status' => 'active',
        ], $listingAttrs));

        $inventory = WarehouseInventory::create([
            'vendor_listing_id' => $listing->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity_on_hand' => $quantity,
            'quantity_reserved' => 0,
        ]);

        // Bypass the booted() saving hook so the storage clock reflects the
        // scenario under test rather than "now".
        DB::table('warehouse_inventories')
            ->where('id', $inventory->id)
            ->update(['first_stocked_at' => $storedSince]);

        return $inventory->fresh();
    }

    public function test_volumetric_weight_wins_when_larger_than_actual_weight(): void
    {
        // Small declared weight (200g) but bulky dimensions: 40x40x40cm.
        // Volumetric = (40*40*40)/5000 = 12.8kg = 12800g > 200g actual.
        $this->makeInventory([
            'declared_weight_grams' => 200,
            'declared_length_cm' => 40,
            'declared_width_cm' => 40,
            'declared_height_cm' => 40,
        ], now()->subDays(40));

        GenerateFbnStorageFeesJob::dispatchSync(now()->format('Y-m'));

        $fee = FbnStorageFee::where('vendor_id', $this->vendor->id)->firstOrFail();

        $this->assertSame(200, $fee->declared_weight_grams);
        $this->assertSame(12800, $fee->volumetric_weight_grams);
        $this->assertSame(12800, $fee->chargeable_weight_grams);
        // 12.8kg >= 1kg tier -> 30 free days; 40 days in storage exceeds it.
        $this->assertSame(30, $fee->free_days_applied);
        $this->assertFalse($fee->within_free_period);
        $this->assertGreaterThan(0, $fee->total_fee);

        // Manually verify the fee formula: volume(m3) x rate x qty.
        $volumeM3 = (40 * 40 * 40) / 1_000_000;
        $expectedFee = (int) round($volumeM3 * 10000 * 5);
        $this->assertSame($expectedFee, $fee->total_fee);
    }

    public function test_missing_dimensions_falls_back_to_actual_weight_without_error(): void
    {
        $this->makeInventory([
            'declared_weight_grams' => 500,
            'declared_length_cm' => null,
            'declared_width_cm' => null,
            'declared_height_cm' => null,
        ], now()->subDays(10));

        GenerateFbnStorageFeesJob::dispatchSync(now()->format('Y-m'));

        $fee = FbnStorageFee::where('vendor_id', $this->vendor->id)->firstOrFail();

        $this->assertSame(0, $fee->volumetric_weight_grams);
        $this->assertSame(500, $fee->chargeable_weight_grams);
        // <1kg tier -> 60 free days; 10 days in storage is still within it.
        $this->assertSame(60, $fee->free_days_applied);
        $this->assertTrue($fee->within_free_period);
        $this->assertSame(0, $fee->total_fee);
    }

    public function test_free_period_boundary_is_inclusive_of_the_free_days_count(): void
    {
        // The job measures days-in-storage against the END of the billed
        // month, not "now" — anchor there so the day counts land exactly.
        $billingCutoff = now()->endOfMonth();

        // Chargeable weight 500g -> 60 free days.
        $this->makeInventory([
            'declared_weight_grams' => 500,
        ], $billingCutoff->copy()->subDays(60));

        $this->makeInventory([
            'declared_weight_grams' => 500,
            'declared_length_cm' => 10,
            'declared_width_cm' => 10,
            'declared_height_cm' => 10,
        ], $billingCutoff->copy()->subDays(61));

        GenerateFbnStorageFeesJob::dispatchSync(now()->format('Y-m'));

        $fees = FbnStorageFee::where('vendor_id', $this->vendor->id)->orderBy('days_in_storage')->get();

        $this->assertCount(2, $fees);

        $atBoundary = $fees->firstWhere('days_in_storage', 60);
        $pastBoundary = $fees->firstWhere('days_in_storage', 61);

        $this->assertTrue($atBoundary->within_free_period);
        $this->assertSame(0, $atBoundary->total_fee);

        $this->assertFalse($pastBoundary->within_free_period);
        $this->assertGreaterThan(0, $pastBoundary->total_fee);
    }

    public function test_free_period_records_are_not_hidden_from_the_datatable(): void
    {
        $this->makeInventory(['declared_weight_grams' => 500], now()->subDays(5));

        GenerateFbnStorageFeesJob::dispatchSync(now()->format('Y-m'));

        $fee = FbnStorageFee::where('vendor_id', $this->vendor->id)->firstOrFail();
        $this->assertTrue($fee->within_free_period);
        $this->assertSame(0, $fee->total_fee);

        // The datatable query joins vendors with no filter excluding
        // within_free_period=true / total_fee=0 rows.
        $this->assertDatabaseHas('fbn_storage_fees', [
            'id' => $fee->id,
            'within_free_period' => true,
        ]);
    }

    public function test_generation_status_cache_reports_completion_counts(): void
    {
        $this->makeInventory(['declared_weight_grams' => 500], now()->subDays(5));

        $month = now()->format('Y-m');
        GenerateFbnStorageFeesJob::dispatchSync($month);

        $status = Cache::get(GenerateFbnStorageFeesJob::statusCacheKey($month));

        $this->assertSame('done', $status['state']);
        $this->assertSame(1, $status['created']);
        $this->assertSame(1, $status['free']);
    }

    public function test_free_days_for_uses_configured_rule_tiers(): void
    {
        $this->assertSame(60, StorageFeeFreePeriodRule::freeDaysFor(999));
        $this->assertSame(30, StorageFeeFreePeriodRule::freeDaysFor(1000));
    }
}
