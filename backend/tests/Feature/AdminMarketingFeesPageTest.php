<?php

namespace Tests\Feature;

use App\Enums\MarketingFeeType;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Country;
use App\Models\MarketingFeeCategorySetting;
use App\Models\MarketingFeeCountrySetting;
use App\Services\NavigationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** P2: admin "Marketing Fees" page. */
class AdminMarketingFeesPageTest extends TestCase
{
    use RefreshDatabase;

    private Country $country;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        app()->setLocale('en');
        foreach (['vendors.assigned_only', 'marketer_commission_settings.view', 'marketer_fee_settings.edit'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'admin']);
        }
        $this->country = Country::factory()->create(['is_active' => true]);
        $this->category = Category::factory()->create();
    }

    private function admin(array $permissions): Admin
    {
        $admin = Admin::factory()->create();
        $admin->givePermissionTo($permissions);

        return $admin;
    }

    private function viewer(): Admin
    {
        return $this->admin(['marketer_commission_settings.view']);
    }

    private function editor(): Admin
    {
        return $this->admin(['marketer_commission_settings.view', 'marketer_fee_settings.edit']);
    }

    public function test_guest_and_users_without_permission_are_blocked(): void
    {
        $this->get(route('admin.marketing-fees.index'))->assertRedirect();
        $this->actingAs($this->admin([]), 'admin')->get(route('admin.marketing-fees.index'))->assertForbidden();
    }

    public function test_viewer_sees_page_with_combined_labels_and_tabs(): void
    {
        $this->actingAs($this->viewer(), 'admin')->get(route('admin.marketing-fees.index'))
            ->assertOk()
            ->assertSee('FBM (cross_dock)', false)
            ->assertSee('FBN (Fulfilled by Nawy)', false)
            ->assertSee('FBP (fbm)', false)
            ->assertSee(__('marketing_fees.tab_report'))
            ->assertSee('data-fee-calculator', false);
    }

    public function test_viewer_cannot_write(): void
    {
        $viewer = $this->viewer();
        $this->actingAs($viewer, 'admin')->post(route('admin.marketing-fees.update-fixed'), [
            'country_id' => $this->country->id, 'fixed_amount' => 1, 'fixed_percentage' => 1,
        ])->assertForbidden();
        $this->actingAs($viewer, 'admin')->post(route('admin.marketing-fees.update-variable'), [
            'country_id' => $this->country->id, 'category_id' => $this->category->id, 'fulfillment_type' => 'fbn', 'fixed_amount' => 1, 'percentage' => 1,
        ])->assertForbidden();
        $this->assertDatabaseCount('marketing_fee_country_settings', 0);
    }

    public function test_fixed_fee_is_saved_then_updated_in_place(): void
    {
        $admin = $this->editor();
        foreach ([[1.9, 0], [2.5, 3.25]] as [$amount, $pct]) {
            $this->actingAs($admin, 'admin')->post(route('admin.marketing-fees.update-fixed'), [
                'country_id' => $this->country->id, 'fixed_amount' => $amount, 'fixed_percentage' => $pct,
            ])->assertRedirect()->assertSessionHasNoErrors();
        }

        $this->assertSame(1, MarketingFeeCountrySetting::count());
        $row = MarketingFeeCountrySetting::first();
        $this->assertEquals(2.5, (float) $row->fixed_amount);
        $this->assertEquals(3.25, (float) $row->fixed_percentage);
        $this->assertSame($admin->id, $row->updated_by_admin_id);
    }

    public function test_variable_fee_is_unique_per_country_category_type_and_deletable(): void
    {
        $admin = $this->editor();
        $payload = ['country_id' => $this->country->id, 'category_id' => $this->category->id, 'fulfillment_type' => 'fbm', 'fixed_amount' => 0, 'percentage' => 10];

        $this->actingAs($admin, 'admin')->post(route('admin.marketing-fees.update-variable'), $payload)->assertSessionHasNoErrors();
        $this->actingAs($admin, 'admin')->post(route('admin.marketing-fees.update-variable'), array_merge($payload, ['percentage' => 12.5]))->assertSessionHasNoErrors();

        $this->assertSame(1, MarketingFeeCategorySetting::count());
        $row = MarketingFeeCategorySetting::first();
        $this->assertSame(MarketingFeeType::Fbm, $row->fulfillment_type);
        $this->assertEquals(12.5, (float) $row->percentage);

        $this->actingAs($admin, 'admin')->delete(route('admin.marketing-fees.destroy-variable', $row))->assertRedirect();
        $this->assertSame(0, MarketingFeeCategorySetting::count());
    }

    public function test_apply_to_all_types_writes_three_rows(): void
    {
        $this->actingAs($this->editor(), 'admin')->post(route('admin.marketing-fees.update-variable'), [
            'country_id' => $this->country->id, 'category_id' => $this->category->id, 'all_types' => 1, 'fixed_amount' => 1, 'percentage' => 5,
        ])->assertSessionHasNoErrors();

        $this->assertSame(3, MarketingFeeCategorySetting::count());
    }

    public function test_validation_rejects_bad_values(): void
    {
        $admin = $this->editor();
        $this->actingAs($admin, 'admin')->post(route('admin.marketing-fees.update-fixed'), [
            'country_id' => $this->country->id, 'fixed_amount' => -1, 'fixed_percentage' => 101,
        ])->assertSessionHasErrors(['fixed_amount', 'fixed_percentage']);

        $this->actingAs($admin, 'admin')->post(route('admin.marketing-fees.update-fixed'), [
            'country_id' => $this->country->id, 'fixed_amount' => 1.12345, 'fixed_percentage' => 'abc',
        ])->assertSessionHasErrors(['fixed_amount', 'fixed_percentage']);

        $this->actingAs($admin, 'admin')->post(route('admin.marketing-fees.update-variable'), [
            'country_id' => $this->country->id, 'category_id' => $this->category->id, 'fulfillment_type' => 'nope', 'fixed_amount' => 1, 'percentage' => 1,
        ])->assertSessionHasErrors(['fulfillment_type']);

        $this->actingAs($admin, 'admin')->post(route('admin.marketing-fees.update-variable'), [
            'country_id' => $this->country->id, 'category_id' => $this->category->id, 'fixed_amount' => 1, 'percentage' => 1,
        ])->assertSessionHasErrors(['fulfillment_type']);

        $this->assertDatabaseCount('marketing_fee_country_settings', 0);
        $this->assertDatabaseCount('marketing_fee_category_settings', 0);
    }

    public function test_nav_item_only_shows_with_view_permission(): void
    {
        $labels = fn () => collect(app(NavigationService::class)->adminNavigation())
            ->pluck('items')->flatten(1)->pluck('route')->all();

        $this->actingAs($this->viewer(), 'admin');
        $this->assertContains('admin.marketing-fees.index', $labels());

        $this->actingAs($this->admin([]), 'admin');
        $this->assertNotContains('admin.marketing-fees.index', $labels());
    }

    public function test_calculator_endpoint_returns_the_plan_example(): void
    {
        MarketingFeeCountrySetting::create(['country_id' => $this->country->id, 'fixed_amount' => 1.9, 'fixed_percentage' => 0]);
        MarketingFeeCategorySetting::create([
            'country_id' => $this->country->id, 'category_id' => $this->category->id, 'fulfillment_type' => 'fbn', 'fixed_amount' => 0, 'percentage' => 10,
        ]);

        $this->actingAs($this->viewer(), 'admin')->postJson(route('admin.marketing-fees.calculate'), [
            'mode' => 'influencer', 'subtotal' => 18.90, 'country_id' => $this->country->id,
            'category_id' => $this->category->id, 'fulfillment_type' => 'fbn',
        ])->assertOk()->assertJson([
            'fixed_fee' => 1.9, 'variable_fee' => 1.89, 'marketing_fees' => 3.79, 'grand_total' => 22.69,
            'type_label' => 'FBN (Fulfilled by Nawy)',
        ]);
    }

    public function test_calculator_affiliate_follow_up_accrual_skips_flat_amounts(): void
    {
        MarketingFeeCountrySetting::create(['country_id' => $this->country->id, 'fixed_amount' => 1.9, 'fixed_percentage' => 0]);
        MarketingFeeCategorySetting::create([
            'country_id' => $this->country->id, 'category_id' => $this->category->id, 'fulfillment_type' => 'fbn', 'fixed_amount' => 0, 'percentage' => 10,
        ]);
        $admin = $this->viewer();
        $body = ['mode' => 'affiliate', 'subtotal' => 20, 'country_id' => $this->country->id, 'category_id' => $this->category->id, 'fulfillment_type' => 'fbn'];

        $this->actingAs($admin, 'admin')->postJson(route('admin.marketing-fees.calculate'), $body)
            ->assertOk()->assertJson(['marketing_fees' => 3.9]);
        $this->actingAs($admin, 'admin')->postJson(route('admin.marketing-fees.calculate'), $body + ['first_order' => false])
            ->assertOk()->assertJson(['marketing_fees' => 2.0]);
    }

    public function test_calculator_requires_permission_and_valid_input(): void
    {
        $body = ['mode' => 'influencer', 'subtotal' => 10, 'country_id' => $this->country->id, 'fulfillment_type' => 'fbn'];
        $this->actingAs($this->admin([]), 'admin')->postJson(route('admin.marketing-fees.calculate'), $body)->assertForbidden();
        $this->actingAs($this->viewer(), 'admin')->postJson(route('admin.marketing-fees.calculate'), ['mode' => 'x'] + $body)->assertUnprocessable();
    }

    public function test_category_search_returns_path_labels(): void
    {
        $parent = Category::factory()->create(['name_en' => 'Electronics Root']);
        $child = Category::factory()->create(['name_en' => 'Phones Leaf', 'parent_id' => $parent->id]);

        $res = $this->actingAs($this->viewer(), 'admin')->getJson(route('admin.marketing-fees.categories', ['q' => 'Phones']))->assertOk();
        $this->assertSame($child->id, $res->json('results.0.id'));
        $this->assertStringContainsString('Electronics Root', $res->json('results.0.text'));
    }
}
