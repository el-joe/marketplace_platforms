<?php

namespace Tests\Feature\Admin;

use App\Models\Activity;
use App\Models\Admin;
use App\Models\Commission;
use App\Models\Marketer;
use App\Models\MarketerCategoryCommission;
use App\Models\SubscriptionPlan;
use App\Models\Vendor;
use App\Services\Admin\Commissions\CommissionTabRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CommissionsControllerTest extends TestCase
{
    use RefreshDatabase;

    /** Every permission that gates at least one tab, so a "full" admin can open all 20. */
    private const ALL_TAB_PERMISSIONS = [
        'categories.view', 'vendors.view', 'admin_listings.view', 'flash_sales.view', 'marketer_campaigns.view',
        'marketers.view', 'marketer_commission_settings.view', 'travel.view',
    ];

    private function admin(array $permissions): Admin
    {
        foreach (array_merge($permissions, ['vendors.assigned_only']) as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'admin']);
        }
        $admin = Admin::factory()->create();
        $admin->givePermissionTo($permissions);

        return $admin;
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function allTabKeys(): array
    {
        $keys = array_keys(CommissionTabRegistry::tabs());

        return array_combine($keys, array_map(fn (string $key) => [$key], $keys));
    }

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $this->get(route('admin.commissions.index'))->assertRedirect(route('admin.login'));
    }

    public function test_forbids_admin_without_any_commission_permission(): void
    {
        $this->actingAs($this->admin(['products.view']), 'admin')
            ->get(route('admin.commissions.index'))
            ->assertForbidden();
    }

    #[DataProvider('allTabKeys')]
    public function test_renders_each_tab_with_its_title_and_effect_badge(string $key): void
    {
        $tab = CommissionTabRegistry::tabs()[$key];

        $this->actingAs($this->admin(self::ALL_TAB_PERMISSIONS), 'admin')
            ->withSession(['locale' => 'en'])
            ->get(route('admin.commissions.index', ['tab' => $key]))
            ->assertOk()
            ->assertSeeText(__('commissions.tabs.'.$key.'.title', [], 'en'))
            ->assertSeeText(__('commissions.effects.'.$tab['effect'], [], 'en'))
            ->assertSee('aria-current="page"', false);
    }

    public function test_registry_covers_all_twenty_audit_items_with_en_and_ar_copy(): void
    {
        $tabs = CommissionTabRegistry::tabs();

        $this->assertSame(range(1, 20), collect($tabs)->pluck('item')->sort()->values()->all());

        foreach (['en', 'ar'] as $locale) {
            foreach ($tabs as $key => $tab) {
                foreach (['title', 'summary', 'pays', 'receives'] as $field) {
                    $this->assertTrue(trans()->hasForLocale("commissions.tabs.$key.$field", $locale), "missing $locale commissions.tabs.$key.$field");
                }
                $this->assertTrue(trans()->hasForLocale('commissions.effects.'.$tab['effect'], $locale));
                $this->assertTrue(trans()->hasForLocale('commissions.access.'.$tab['access'], $locale));
                $this->assertTrue(trans()->hasForLocale('commissions.groups.'.$tab['group'], $locale));
            }
        }
    }

    public function test_hides_tabs_the_admin_has_no_permission_for_and_falls_back_to_first_visible(): void
    {
        $this->actingAs($this->admin(['categories.view']), 'admin')
            ->withSession(['locale' => 'en'])
            ->get(route('admin.commissions.index', ['tab' => 'vendor-rules']))
            ->assertOk()
            ->assertSeeText(__('commissions.tabs.platform-rules.title', [], 'en'))
            ->assertDontSeeText(__('commissions.tabs.vendor-rules.title', [], 'en'))
            ->assertDontSee('name="rate_pct"', false);
    }

    public function test_unknown_tab_falls_back_to_the_first_visible_tab(): void
    {
        $this->actingAs($this->admin(self::ALL_TAB_PERMISSIONS), 'admin')
            ->withSession(['locale' => 'en'])
            ->get(route('admin.commissions.index', ['tab' => 'does-not-exist']))
            ->assertOk()
            ->assertSeeText(__('commissions.tabs.platform-rules.title', [], 'en'));
    }

    public function test_group_and_tab_links_point_at_the_single_commissions_route(): void
    {
        $this->actingAs($this->admin(self::ALL_TAB_PERMISSIONS), 'admin')
            ->get(route('admin.commissions.index'))
            ->assertOk()
            ->assertSee(route('admin.commissions.index', ['tab' => 'vendor-rules']), false)
            ->assertSee(route('admin.commissions.index', ['tab' => 'campaigns']), false)
            ->assertSee(route('admin.commissions.index', ['tab' => 'acquisition']), false);
    }

    public function test_arabic_locale_renders_right_to_left_with_arabic_copy(): void
    {
        $this->actingAs($this->admin(self::ALL_TAB_PERMISSIONS), 'admin')
            ->withSession(['locale' => 'ar'])
            ->get(route('admin.commissions.index', ['tab' => 'plan-discount']))
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSeeText(__('commissions.tabs.plan-discount.title', [], 'ar'))
            ->assertSeeText(__('commissions.effects.not_applied', [], 'ar'));
    }

    public function test_viewing_every_tab_writes_nothing(): void
    {
        $vendor = Vendor::factory()->create();
        Commission::create(['vendor_id' => $vendor->id, 'rate_pct' => 7, 'rate_type' => 'flat', 'min_commission' => 0, 'effective_from' => now()->toDateString(), 'priority' => 0]);
        $admin = $this->admin(self::ALL_TAB_PERMISSIONS);
        $before = [Commission::count(), Activity::count(), Commission::first()->only(['rate_pct', 'priority'])];

        foreach (array_keys(CommissionTabRegistry::tabs()) as $key) {
            $this->actingAs($admin, 'admin')->get(route('admin.commissions.index', ['tab' => $key]))->assertOk();
        }

        $this->assertSame($before, [Commission::count(), Activity::count(), Commission::first()->only(['rate_pct', 'priority'])]);
    }

    public function test_plan_discount_tab_labels_the_discount_as_not_applied_at_checkout(): void
    {
        SubscriptionPlan::create([
            'name_en' => 'Gold', 'name_ar' => 'ذهبي', 'price' => 100, 'currency' => 'USD', 'billing_cycle' => 'monthly',
            'commission_discount_pct' => 25, 'is_active' => true, 'sort_order' => 1,
        ]);

        $this->actingAs($this->admin(['vendors.view']), 'admin')
            ->withSession(['locale' => 'en'])
            ->get(route('admin.commissions.index', ['tab' => 'plan-discount']))
            ->assertOk()
            ->assertSeeText('Gold')
            ->assertSeeText('25%')
            ->assertSeeText(__('commissions.notices.plan_discount_not_applied', [], 'en'));
    }

    public function test_listing_percentages_tab_reports_missing_schema_columns(): void
    {
        // vendor_listings has no influencer/affiliate percentage columns and no migration creates them.
        $this->actingAs($this->admin(['marketer_commission_settings.view']), 'admin')
            ->withSession(['locale' => 'en'])
            ->get(route('admin.commissions.index', ['tab' => 'listing-percentages']))
            ->assertOk()
            ->assertSeeText(__('commissions.notices.listing_pct_columns_missing', [], 'en'));
    }

    public function test_legacy_stores_tab_flags_mirror_rows_without_a_resolver_rule(): void
    {
        $marketer = Marketer::create([
            'name' => 'M', 'email' => 'm@example.test', 'phone' => '+971500000009',
            'marketer_type' => 'influencer', 'global_status' => 'active', 'approved_at' => now(),
        ]);
        MarketerCategoryCommission::create([
            'marketer_id' => $marketer->id, 'category_id' => null, 'commission_mode' => 'percentage', 'commission_rate' => 10,
        ]);

        $this->actingAs($this->admin(['marketers.view']), 'admin')
            ->withSession(['locale' => 'en'])
            ->get(route('admin.commissions.index', ['tab' => 'legacy-stores']))
            ->assertOk()
            ->assertSeeText(__('commissions.notices.legacy_orphans', [], 'en'))
            ->assertSeeText($marketer->id);
    }

    public function test_scoped_admin_sees_only_assigned_vendors_and_platform_rules(): void
    {
        $admin = $this->admin(['vendors.view', 'vendors.assigned_only']);
        $mine = Vendor::factory()->create(['store_name' => 'Assigned Store', 'account_manager_admin_id' => $admin->id, 'commission_rate' => 4]);
        $other = Vendor::factory()->create(['store_name' => 'Foreign Store', 'commission_rate' => 9]);
        foreach ([$mine, $other] as $vendor) {
            Commission::create(['vendor_id' => $vendor->id, 'rate_pct' => 5, 'rate_type' => 'flat', 'min_commission' => 0, 'effective_from' => now()->toDateString(), 'priority' => 0]);
        }

        $this->actingAs($admin, 'admin')->get(route('admin.commissions.index', ['tab' => 'vendor-rules']))
            ->assertOk()->assertSeeText('Assigned Store')->assertDontSeeText('Foreign Store');

        $this->actingAs($admin, 'admin')->get(route('admin.commissions.index', ['tab' => 'vendor-flat-rate']))
            ->assertOk()->assertSeeText('Assigned Store')->assertDontSeeText('Foreign Store');
    }
}
