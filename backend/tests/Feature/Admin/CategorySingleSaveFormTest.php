<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Attribute;
use App\Models\Category;
use App\Models\CategoryShippingMethod;
use App\Models\Country;
use App\Models\CountryCategory;
use App\Models\ShippingMethod;
use App\Services\CategoryCountryAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Client #5/#8: the category form holds every section (incl. shipping methods,
 * commissions, tiers and country availability) and saves them in one request,
 * on create as well as on edit ("Save all").
 */
class CategorySingleSaveFormTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        Storage::fake('public');

        $this->admin = Admin::factory()->create();
        $this->admin->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'admin']));
        $this->admin->givePermissionTo(Permission::firstOrCreate(['name' => 'vendors.assigned_only', 'guard_name' => 'admin']));
    }

    private function shippingMethod(): ShippingMethod
    {
        return ShippingMethod::create(['name' => 'Std '.Str::random(4), 'code' => 'std-'.Str::lower(Str::random(6)), 'is_active' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function basePayload(array $overrides = []): array
    {
        return array_merge([
            'name_en' => 'Garden '.Str::random(5),
            'name_ar' => 'حديقة',
            'is_active' => '1',
            'is_visible' => '1',
            'is_featured' => '0',
            'has_filters' => '0',
            'requires_contract' => '0',
            'sync' => ['attributes', 'shipping_methods', 'country_commissions', 'tiers', 'availability'],
            'availability_mode' => 'all',
        ], $overrides);
    }

    public function test_create_saves_every_section_in_one_request(): void
    {
        $oman = Country::factory()->create(['is_active' => true]);
        $bahrain = Country::factory()->create(['is_active' => true]);
        $method = $this->shippingMethod();
        $attribute = Attribute::forceCreate(['id' => (string) Str::uuid(), 'name_en' => 'Color', 'name_ar' => 'اللون', 'code' => 'color-'.Str::lower(Str::random(5)), 'type' => 'select']);

        $response = $this->actingAs($this->admin, 'admin')->postJson(route('admin.categories.store'), $this->basePayload([
            'name_en' => 'Garden tools',
            'influencer_sample_qty' => '3',
            'attributes' => [['attribute_id' => $attribute->id, 'is_required' => '1', 'sort_order' => '0']],
            'shipping_methods' => [[
                'shipping_method_id' => $method->id,
                'is_default' => '1',
                'is_available_for_express_fbn' => '1',
                'is_available_for_merchant_fbp' => '0',
            ]],
            'country_commissions' => [$oman->id => ['commission_fbp_pct' => '7.5', 'commission_fbp_fixed' => '']],
            'tiers' => [
                ['id' => '', 'country_id' => '', 'price_from' => '0', 'price_to' => '100', 'commission_rate' => '10', 'min_commission' => '0'],
                ['id' => '', 'country_id' => $oman->id, 'price_from' => '0', 'price_to' => '', 'commission_rate' => '6', 'min_commission' => '1'],
            ],
            'availability_mode' => 'only',
            'availability_countries' => [$oman->id],
            'image' => UploadedFile::fake()->image('cat.png'),
        ]));

        $response->assertOk()->assertJsonPath('success', true);

        $category = Category::where('name_en', 'Garden tools')->firstOrFail();
        $response->assertJsonPath('redirect', route('admin.categories.edit', $category->id));

        $this->assertSame(3, $category->influencer_sample_qty);
        $this->assertDatabaseHas('category_attributes', ['category_id' => $category->id, 'attribute_id' => $attribute->id, 'is_required' => 1]);
        $this->assertDatabaseHas('category_shipping_methods', ['category_id' => $category->id, 'shipping_method_id' => $method->id, 'is_default' => 1]);
        $this->assertSame(2, $category->commissionTiers()->count());
        $this->assertEquals(7.5, (float) CountryCategory::where(['category_id' => $category->id, 'country_id' => $oman->id])->value('commission_fbp_pct'));
        $this->assertFalse((bool) CountryCategory::where(['category_id' => $category->id, 'country_id' => $bahrain->id])->value('is_available'));
        $this->assertNotNull($category->fresh()->image_url);
    }

    public function test_invalid_section_rejects_the_whole_save_and_names_the_field(): void
    {
        $before = Category::count();

        $this->actingAs($this->admin, 'admin')->postJson(route('admin.categories.store'), $this->basePayload([
            'tiers' => [
                ['country_id' => '', 'price_from' => '0', 'price_to' => '100', 'commission_rate' => '10'],
                ['country_id' => '', 'price_from' => '50', 'price_to' => '200', 'commission_rate' => '8'],
            ],
            'availability_mode' => 'except',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tiers.1.price_from', 'availability_countries']);

        $this->assertSame($before, Category::count());
    }

    public function test_save_all_on_edit_updates_lists_and_switches_flags_off(): void
    {
        $category = Category::factory()->create();
        $kept = $this->shippingMethod();
        $dropped = $this->shippingMethod();
        foreach ([$kept, $dropped] as $i => $method) {
            CategoryShippingMethod::create(['category_id' => $category->id, 'shipping_method_id' => $method->id, 'is_default' => $i === 0, 'display_priority' => $i]);
        }
        $tier = $category->commissionTiers()->create(['price_from' => 0, 'price_to' => null, 'commission_rate' => 5, 'min_commission' => 0, 'sort_order' => 0]);

        $this->actingAs($this->admin, 'admin')->putJson(route('admin.categories.update', $category->id), $this->basePayload([
            'name_en' => $category->name_en,
            'is_active' => '0',
            'is_visible' => '0',
            'shipping_methods' => [['shipping_method_id' => $kept->id, 'is_default' => '1', 'is_available_for_express_fbn' => '1', 'is_available_for_merchant_fbp' => '1']],
            'tiers' => [['id' => $tier->id, 'country_id' => '', 'price_from' => '0', 'price_to' => '', 'commission_rate' => '9', 'min_commission' => '0']],
        ]))->assertOk();

        $category->refresh();
        $this->assertFalse($category->is_active);
        $this->assertFalse($category->is_visible);
        $this->assertSame([$kept->id], CategoryShippingMethod::where('category_id', $category->id)->pluck('shipping_method_id')->all());
        $this->assertSame([$tier->id], $category->commissionTiers()->pluck('id')->all());
        $this->assertEquals(9, (float) $tier->fresh()->commission_rate);

        // An emptied list (marked in sync[]) is cleared, not ignored.
        $this->actingAs($this->admin, 'admin')->putJson(route('admin.categories.update', $category->id), $this->basePayload([
            'name_en' => $category->name_en,
        ]))->assertOk();

        $this->assertSame(0, CategoryShippingMethod::where('category_id', $category->id)->count());
        $this->assertSame(0, $category->commissionTiers()->count());
    }

    public function test_category_form_and_country_page_share_the_same_exception_rows(): void
    {
        $category = Category::factory()->create();
        $oman = Country::factory()->create(['is_active' => true]);
        $bahrain = Country::factory()->create(['is_active' => true]);

        $this->actingAs($this->admin, 'admin')->putJson(route('admin.categories.update', $category->id), $this->basePayload([
            'name_en' => $category->name_en,
            'availability_mode' => 'except',
            'availability_countries' => [$bahrain->id],
        ]))->assertOk();

        $this->assertFalse((bool) CountryCategory::where(['category_id' => $category->id, 'country_id' => $bahrain->id])->value('is_available'));
        $this->assertNull(CountryCategory::where(['category_id' => $category->id, 'country_id' => $oman->id])->first());

        // Country page re-enables it → category form now reads "all countries".
        $this->actingAs($this->admin, 'admin')->postJson(route('admin.countries.category-overrides.update', $bahrain->id), [
            'overrides' => [['category_id' => $category->id, 'is_available' => true]],
        ])->assertOk();

        $this->assertSame(
            ['mode' => 'all', 'country_ids' => []],
            app(CategoryCountryAvailabilityService::class)->describe($category),
        );
    }

    public function test_create_and_edit_pages_render_every_section(): void
    {
        $category = Category::factory()->create();

        foreach ([route('admin.categories.create'), route('admin.categories.edit', $category->id)] as $url) {
            $html = $this->actingAs($this->admin, 'admin')->get($url)->assertOk()->getContent();
            foreach (['general', 'status', 'countries', 'attributes', 'shipping', 'commissions', 'seo', 'marketers'] as $section) {
                $this->assertStringContainsString('id="sec-'.$section.'"', $html);
            }
        }
    }
}
