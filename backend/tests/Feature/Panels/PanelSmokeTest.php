<?php

namespace Tests\Feature\Panels;

use App\Models\Admin;
use App\Models\VendorAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * enhancement.md P-23: regression coverage for the 9 confirmed panel bugs.
 * Each test hits the exact route that used to 500/404 with a real,
 * correctly-scoped, logged-in user for that panel/guard.
 */
class PanelSmokeTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $scenario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scenario = MarketplaceScenario::make()->build();

        if (empty($this->scenario->country->site_code)) {
            $this->scenario->country->update(['site_code' => strtolower($this->scenario->country->iso_code_2)]);
        }
    }

    private function superAdmin(): Admin
    {
        $admin = Admin::factory()->create();

        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'admin']);
        $admin->assignRole($role);

        // ScopeAdminToAssignedVendor calls hasPermissionTo() directly (not
        // through Gate), so the Gate::before super_admin bypass doesn't
        // cover it — the permission must actually exist and be granted.
        $permission = Permission::firstOrCreate(['name' => 'vendors.assigned_only', 'guard_name' => 'admin']);
        $admin->givePermissionTo($permission);

        return $admin;
    }

    private function superVendorAdmin(): VendorAdmin
    {
        $vendorAdmin = VendorAdmin::create([
            'vendor_id' => $this->scenario->vendor->id,
            'name'      => 'Panel Smoke Vendor Admin',
            'email'     => 'panel-smoke-' . Str::random(8) . '@example.test',
            'password'  => bcrypt('password'),
            'role'      => 'owner',
            'is_owner'  => true,
            'is_active' => true,
        ]);

        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'vendor']);
        $vendorAdmin->assignRole($role);

        // Also grant the concrete permissions the routes check explicitly,
        // in case the super_admin Gate::before bypass isn't reached for a
        // given middleware implementation.
        foreach (['returns.view', 'returns.process'] as $name) {
            $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'vendor']);
            $vendorAdmin->givePermissionTo($permission);
        }

        return $vendorAdmin;
    }

    /** Item 1: Admin flash-sale analytics used `fs.title`, a column that doesn't exist. */
    public function test_admin_flash_sale_analytics_does_not_500(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin, 'admin')
            ->getJson(route('admin.analytics.flash-sales'));

        $response->assertOk();
    }

    /** Item 2: fallback-rules was swallowed by the {shippingCompany} resource route. */
    public function test_admin_shipping_company_fallback_rules_route_resolves(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.shipping-companies.fallback-rules.index'));

        $response->assertOk();
    }

    /** Item 3: admin.docs.panels.marketer and admin.marketers.payouts.index were undefined. */
    public function test_admin_docs_index_does_not_500(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.docs.index'));

        $response->assertOk();
    }

    public function test_admin_docs_finance_feature_page_does_not_500(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.docs.features.finance'));

        $response->assertOk();
    }

    public function test_admin_docs_marketer_panel_page_does_not_500(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.docs.panels.marketer'));

        $response->assertOk();
    }

    /** Item 4: VendorAdmin had no `country` relation; partner wallet page used it. */
    public function test_partner_wallet_page_does_not_500(): void
    {
        $vendorAdmin = $this->superVendorAdmin();

        $response = $this->actingAs($vendorAdmin, 'vendor')
            ->get(route('partner.wallet.index'));

        $response->assertOk();
    }

    /** Item 5: `customers.first_name` doesn't exist — customers only have `name`. */
    public function test_partner_returns_index_does_not_500(): void
    {
        $vendorAdmin = $this->superVendorAdmin();

        $response = $this->actingAs($vendorAdmin, 'vendor')
            ->get(route('partner.returns.index'));

        $response->assertOk();
    }

    /** Item 6: Category::vendorListings() didn't exist; used by partner ads/categories. */
    public function test_partner_ads_categories_does_not_500(): void
    {
        $vendorAdmin = $this->superVendorAdmin();

        $response = $this->actingAs($vendorAdmin, 'vendor')
            ->getJson(route('partner.ads.categories'));

        $response->assertOk();
    }

    /** Item 7: navActive() was declared inline and fatal on a second render in-process. */
    public function test_delivery_dashboard_layout_renders_twice_in_one_process(): void
    {
        $agent = $this->scenario->deliveryAgent;

        $response = $this->actingAs($agent, 'delivery')
            ->get(route('delivery.dashboard'));
        $response->assertOk();

        // Rendering the same Blade layout a second time in the same PHP
        // process is exactly the scenario that used to fatal with
        // "Cannot redeclare navActive()".
        $second = $this->actingAs($agent, 'delivery')
            ->get(route('delivery.dashboard'));
        $second->assertOk();
    }

    /** Item 8: customer catalog-listings 500, covered by P-17 — verify it stays fixed. */
    public function test_customer_catalog_listings_does_not_500(): void
    {
        $response = $this->getJson("/api/customer/v1/{$this->scenario->country->site_code}/catalog-listings");

        $response->assertOk();
    }

    /**
     * Item 9: customer pages/home 404 (P-20) is intentional — "home" is served
     * by its own dedicated endpoint (customer.home.index), not the generic
     * pages/{type} content-page renderer, so pages/home correctly 404s.
     */
    public function test_customer_pages_home_404_is_intentional_not_a_bug(): void
    {
        $pagesResponse = $this->getJson("/api/customer/v1/{$this->scenario->country->site_code}/pages/home");
        $pagesResponse->assertStatus(404);

        $homeResponse = $this->getJson("/api/customer/v1/{$this->scenario->country->site_code}/home");
        $homeResponse->assertOk();
    }
}
