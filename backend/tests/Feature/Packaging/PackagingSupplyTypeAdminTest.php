<?php

namespace Tests\Feature\Packaging;

use App\Models\Admin;
use App\Models\Country;
use App\Models\PackagingSupply;
use App\Models\PackagingSupplyType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Client item #12: packaging types are admin-managed (AR/EN names), replacing the fixed list,
 * gated by `packaging.manage`; inactive types stay on existing items but cannot be chosen for new ones.
 */
class PackagingSupplyTypeAdminTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['packaging.manage', 'vendors.assigned_only'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'admin']);
        }

        $this->admin = Admin::factory()->create();
        $this->admin->givePermissionTo('packaging.manage');
    }

    /**
     * @return array<string, mixed>
     */
    private function itemPayload(PackagingSupplyType $type, Country $country, array $overrides = []): array
    {
        return array_merge([
            'name_en' => 'Kraft mailer',
            'name_ar' => 'ظرف كرافت',
            'packaging_supply_type_id' => $type->id,
            'is_active' => '1',
            'country_pricing' => [
                ['country_id' => $country->id, 'unit_cost' => '0.50', 'stock_available' => 100, 'is_active' => '1'],
            ],
        ], $overrides);
    }

    public function test_migration_seeds_the_former_fixed_types_with_their_codes_and_labels(): void
    {
        $seeded = PackagingSupplyType::whereIn('code', ['box', 'bag', 'tape', 'label', 'bubble_wrap', 'other'])
            ->pluck('name_en', 'code')
            ->all();

        $this->assertEquals([
            'box' => 'Box', 'bag' => 'Bag', 'tape' => 'Tape',
            'label' => 'Label', 'bubble_wrap' => 'Bubble Wrap', 'other' => 'Other',
        ], $seeded);
        $this->assertSame('صندوق', PackagingSupplyType::where('code', 'box')->value('name_ar'));
    }

    public function test_type_routes_require_the_packaging_permission(): void
    {
        $outsider = Admin::factory()->create();
        $type = PackagingSupplyType::factory()->create();

        $this->actingAs($outsider, 'admin')->get(route('admin.packaging.types.index'))->assertForbidden();
        $this->actingAs($outsider, 'admin')
            ->postJson(route('admin.packaging.types.store'), ['name_en' => 'Pouch', 'name_ar' => 'حقيبة'])
            ->assertForbidden();
        $this->actingAs($outsider, 'admin')->deleteJson(route('admin.packaging.types.destroy', $type))->assertForbidden();

        $this->assertDatabaseMissing('packaging_supply_types', ['name_en' => 'Pouch']);
        $this->assertModelExists($type);
    }

    public function test_admin_creates_a_type_with_a_code_generated_from_the_english_name(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.packaging.types.store'), ['name_en' => 'Padded Envelope', 'name_ar' => 'ظرف مبطن'])
            ->assertOk()
            ->assertJsonPath('data.code', 'padded_envelope');

        $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.packaging.types.store'), ['name_en' => 'Padded envelope', 'name_ar' => 'ظرف مبطن 2'])
            ->assertOk()
            ->assertJsonPath('data.code', 'padded_envelope_2');

        $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.packaging.types.store'), ['name_en' => 'X', 'name_ar' => 'س', 'code' => 'box'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_update_renames_the_type_but_keeps_its_code(): void
    {
        $type = PackagingSupplyType::factory()->create(['code' => 'pouch']);

        $this->actingAs($this->admin, 'admin')
            ->putJson(route('admin.packaging.types.update', $type), [
                'name_en' => 'Zip pouch', 'name_ar' => 'كيس بسحاب', 'code' => 'changed', 'is_active' => false,
            ])
            ->assertOk();

        $type->refresh();
        $this->assertSame(['pouch', 'Zip pouch', 'كيس بسحاب', false], [$type->code, $type->name_en, $type->name_ar, $type->is_active]);
    }

    public function test_a_type_in_use_cannot_be_deleted_but_an_unused_one_can(): void
    {
        $used = PackagingSupplyType::factory()->create();
        PackagingSupply::factory()->create(['packaging_supply_type_id' => $used->id]);
        $unused = PackagingSupplyType::factory()->create();

        $this->actingAs($this->admin, 'admin')->deleteJson(route('admin.packaging.types.destroy', $used))->assertUnprocessable();
        $this->actingAs($this->admin, 'admin')->deleteJson(route('admin.packaging.types.destroy', $unused))->assertOk();

        $this->assertModelExists($used);
        $this->assertModelMissing($unused);
    }

    public function test_item_can_be_created_with_a_newly_added_type_and_mirrors_its_code(): void
    {
        $country = Country::factory()->create();
        $typeId = $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.packaging.types.store'), ['name_en' => 'Gift wrap', 'name_ar' => 'ورق هدايا'])
            ->json('data.id');

        $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.packaging.catalog.store'), $this->itemPayload(PackagingSupplyType::find($typeId), $country))
            ->assertOk();

        $supply = PackagingSupply::where('name_en', 'Kraft mailer')->firstOrFail();
        $this->assertSame($typeId, $supply->packaging_supply_type_id);
        $this->assertSame('gift_wrap', $supply->type);
    }

    public function test_inactive_type_is_rejected_for_new_items_but_kept_on_existing_ones(): void
    {
        $country = Country::factory()->create();
        $inactive = PackagingSupplyType::factory()->inactive()->create();

        $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.packaging.catalog.store'), $this->itemPayload($inactive, $country))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('packaging_supply_type_id');

        $existing = PackagingSupply::factory()->create(['packaging_supply_type_id' => $inactive->id]);

        $this->actingAs($this->admin, 'admin')
            ->putJson(route('admin.packaging.catalog.update', $existing), $this->itemPayload($inactive, $country, ['name_en' => 'Renamed']))
            ->assertOk();

        $this->assertSame(['Renamed', $inactive->id], [$existing->fresh()->name_en, $existing->fresh()->packaging_supply_type_id]);
    }

    public function test_catalog_filter_lists_all_types_by_localized_name_and_flags_inactive_ones(): void
    {
        PackagingSupplyType::factory()->create(['name_en' => 'Pouch', 'name_ar' => 'حقيبة صغيرة']);
        PackagingSupplyType::factory()->inactive()->create(['name_en' => 'Crate', 'name_ar' => 'صندوق خشبي']);
        app()->setLocale('ar');

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.packaging.catalog'))
            ->assertOk()
            ->assertSee('حقيبة صغيرة')
            ->assertSee('صندوق خشبي '.__('admin.packaging_catalog.type_inactive_suffix', [], 'ar'));
    }
}
