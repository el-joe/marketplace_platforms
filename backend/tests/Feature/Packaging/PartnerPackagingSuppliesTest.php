<?php

namespace Tests\Feature\Packaging;

use App\Models\Country;
use App\Models\PackagingSupply;
use App\Models\PackagingSupplyCountry;
use App\Models\PackagingSupplyRequest;
use App\Models\PackagingSupplyType;
use App\Models\Vendor;
use App\Models\VendorAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Partner packaging screens: admin-defined types appear by localized name (#12) and the
 * "my requests" date filter uses the seller's local day, not the UTC day (#13 hardening).
 */
class PartnerPackagingSuppliesTest extends TestCase
{
    use RefreshDatabase;

    private Vendor $vendor;

    private VendorAdmin $vendorAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $country = Country::factory()->create(['timezone' => 'Asia/Muscat', 'currency_code' => 'OMR']);
        $this->vendor = Vendor::factory()->create(['country_id' => $country->id, 'vendor_type' => 'product_vendor']);
        $this->vendorAdmin = VendorAdmin::create([
            'vendor_id' => $this->vendor->id, 'name' => 'Owner', 'email' => 'owner-'.Str::random(8).'@example.test',
            'password' => bcrypt('password'), 'role' => 'owner', 'is_owner' => true, 'is_active' => true,
        ]);
    }

    public function test_catalog_shows_custom_type_by_localized_name(): void
    {
        $type = PackagingSupplyType::factory()->create(['name_en' => 'Gift wrap', 'name_ar' => 'ورق هدايا']);
        $supply = PackagingSupply::factory()->create(['packaging_supply_type_id' => $type->id]);
        PackagingSupplyCountry::create(['packaging_supply_id' => $supply->id, 'country_id' => $this->vendor->country_id, 'unit_cost' => 0.5, 'stock_available' => 10]);
        app()->setLocale('ar');

        $this->actingAs($this->vendorAdmin, 'vendor')
            ->get(route('partner.packaging-supplies.index'))
            ->assertOk()
            ->assertSee('ورق هدايا');
    }

    public function test_my_requests_date_filter_uses_the_sellers_local_day(): void
    {
        foreach (['PKG-LATE-22' => '2026-11-22 19:59:59', 'PKG-EARLY-23' => '2026-11-22 20:30:00'] as $number => $utc) {
            $request = PackagingSupplyRequest::create([
                'request_number' => $number, 'vendor_id' => $this->vendor->id, 'status' => 'pending',
                'total_cost' => 1, 'delivery_fee' => 0, 'currency' => 'OMR',
            ]);
            $request->forceFill(['created_at' => Carbon::parse($utc, 'UTC')])->save();
        }

        $this->actingAs($this->vendorAdmin, 'vendor')
            ->get(route('partner.packaging-supplies.my-requests', ['date_from' => '2026-11-23', 'date_to' => '2026-11-23']))
            ->assertOk()
            ->assertSee('PKG-EARLY-23')
            ->assertDontSee('PKG-LATE-22');
    }
}
