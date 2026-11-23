<?php

namespace Tests\Feature\Packaging;

use App\Models\Admin;
use App\Models\Country;
use App\Models\PackagingSupply;
use App\Models\PackagingSupplyCountry;
use App\Models\PackagingSupplyType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Client item #29: the catalog list showed "Free" / "∞" (unused legacy columns) although per-country
 * price and stock were saved. The list now shows each country's price · stock and honours a country filter.
 */
class PackagingCatalogListTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    private Country $oman;

    private Country $uae;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['packaging.manage', 'vendors.assigned_only'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'admin']);
        }

        $this->admin = Admin::factory()->create();
        $this->admin->givePermissionTo('packaging.manage');

        $this->oman = Country::factory()->create(['currency_code' => 'OMR']);
        $this->uae = Country::factory()->create(['currency_code' => 'AED']);
    }

    private function supplyPricedInBothCountries(string $name): PackagingSupply
    {
        $supply = PackagingSupply::factory()->create(['name_en' => $name]);
        PackagingSupplyCountry::create(['packaging_supply_id' => $supply->id, 'country_id' => $this->oman->id, 'unit_cost' => 0.5, 'stock_available' => 100]);
        PackagingSupplyCountry::create(['packaging_supply_id' => $supply->id, 'country_id' => $this->uae->id, 'unit_cost' => 0.6, 'stock_available' => 50]);

        return $supply;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, array<string, mixed>>
     */
    private function rows(array $params = []): array
    {
        $rows = $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.packaging.catalog.datatable'), array_merge(['draw' => 1, 'start' => 0, 'length' => 100], $params))
            ->assertOk()
            ->json('data');

        return collect($rows)->keyBy(fn ($row) => $this->nameOf($row))->all();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function nameOf(array $row): string
    {
        return preg_match('/<div class="font-medium[^"]*">([^<]*)</', $row['name'], $match) ? $match[1] : '';
    }

    public function test_list_shows_each_countrys_price_and_stock_instead_of_free_and_infinity(): void
    {
        $this->supplyPricedInBothCountries('Box M');

        $pricing = $this->rows()['Box M']['pricing'];

        $this->assertStringContainsString($this->oman->iso_code_2.'</span> <span dir="ltr">0.50 OMR · 100', $pricing);
        $this->assertStringContainsString($this->uae->iso_code_2.'</span> <span dir="ltr">0.60 AED · 50', $pricing);
        $this->assertStringNotContainsString('Free', $pricing);
        $this->assertStringNotContainsString('∞', $pricing);
    }

    public function test_unlimited_stock_and_zero_cost_are_labelled_per_country(): void
    {
        $supply = PackagingSupply::factory()->create(['name_en' => 'Tape roll']);
        PackagingSupplyCountry::create(['packaging_supply_id' => $supply->id, 'country_id' => $this->oman->id, 'unit_cost' => 0, 'stock_available' => null]);

        $pricing = $this->rows()['Tape roll']['pricing'];

        $this->assertStringContainsString(__('admin.packaging_catalog.free').' · '.__('admin.packaging_catalog.unlimited'), $pricing);
    }

    public function test_country_filter_limits_rows_and_shows_only_that_countrys_values(): void
    {
        $this->supplyPricedInBothCountries('Box M');
        $omanOnly = PackagingSupply::factory()->create(['name_en' => 'Oman only bag']);
        PackagingSupplyCountry::create(['packaging_supply_id' => $omanOnly->id, 'country_id' => $this->oman->id, 'unit_cost' => 0.2, 'stock_available' => 5]);

        $rows = $this->rows(['country_id' => $this->uae->id]);

        $this->assertSame(['Box M'], array_keys($rows));
        $pricing = $rows['Box M']['pricing'];
        $this->assertStringContainsString('0.60 AED · 50', $pricing);
        $this->assertStringNotContainsString('OMR', $pricing);
    }

    public function test_server_side_search_and_type_filter_still_work(): void
    {
        $this->supplyPricedInBothCountries('Box M');
        $pouchType = PackagingSupplyType::factory()->create(['name_en' => 'Pouch']);
        PackagingSupply::factory()->create(['name_en' => 'Zip pouch', 'packaging_supply_type_id' => $pouchType->id]);

        $this->assertSame(['Zip pouch'], array_keys($this->rows(['search' => ['value' => 'Zip']])));
        $this->assertSame(['Zip pouch'], array_keys($this->rows(['type_id' => $pouchType->id])));
    }
}
