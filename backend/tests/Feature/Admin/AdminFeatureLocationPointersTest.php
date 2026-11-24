<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Country;
use App\Services\NavigationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Client retest R10/R11: QA looked for distance pricing on Delivery zones and for the product-link / QR commission
 * on Subscription plans. Both pages now point to where the setting lives, and the sidebar lists both settings
 * where admins look for them, gated by the permission that opens the target page.
 */
class AdminFeatureLocationPointersTest extends TestCase
{
    use RefreshDatabase;

    private const PERMISSIONS = ['delivery.view', 'settings.view', 'subscriptions.view', 'product_link_commission.view', 'vendors.assigned_only'];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (self::PERMISSIONS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'admin']);
        }
    }

    /**
     * @param  list<string>  $permissions
     */
    private function adminWith(array $permissions): Admin
    {
        $admin = Admin::factory()->create();
        $admin->givePermissionTo($permissions);

        return $admin;
    }

    /**
     * @return list<string>
     */
    private function sidebarUrls(): array
    {
        $urls = [];
        foreach (app(NavigationService::class)->adminNavigation() as $group) {
            foreach ($group['items'] as $item) {
                $urls[] = route($item['route'], $item['route_params'] ?? []);
            }
        }

        return $urls;
    }

    public function test_delivery_zones_page_links_to_the_distance_pricing_tab(): void
    {
        $this->actingAs($this->adminWith(['delivery.view', 'settings.view']), 'admin')
            ->get(route('admin.delivery.zones.index'))
            ->assertOk()
            ->assertSee(__('admin.delivery_section.distance_pricing_pointer_title'))
            ->assertSee(route('admin.shipping-zones.index', ['tab' => 'distance']), false);
    }

    public function test_delivery_zones_pointer_has_no_link_without_settings_view(): void
    {
        $this->actingAs($this->adminWith(['delivery.view']), 'admin')
            ->get(route('admin.delivery.zones.index'))
            ->assertOk()
            ->assertSee(__('admin.delivery_section.distance_pricing_pointer_title'))
            ->assertDontSee(route('admin.shipping-zones.index', ['tab' => 'distance']), false);
    }

    public function test_shipping_zones_opens_on_the_distance_tab_when_asked(): void
    {
        Country::factory()->create();

        $this->actingAs($this->adminWith(['settings.view']), 'admin')
            ->get(route('admin.shipping-zones.index', ['tab' => 'distance']))
            ->assertOk()
            ->assertSee("activeTab: 'distance'", false)
            ->assertSee(__('distance_delivery.admin.select_zone_for_distance'));
    }

    public function test_subscription_plans_page_links_to_the_product_link_commission(): void
    {
        $this->actingAs($this->adminWith(['subscriptions.view', 'product_link_commission.view']), 'admin')
            ->get(route('admin.subscriptions.plans.index'))
            ->assertOk()
            ->assertSee(__('admin.subscriptions.product_link_pointer_body'))
            ->assertSee(route('admin.product-link-commission.index'), false);
    }

    public function test_sidebar_lists_distance_pricing_and_product_link_commission_only_with_their_permissions(): void
    {
        $distanceUrl = route('admin.shipping-zones.index', ['tab' => 'distance']);
        $commissionUrl = route('admin.product-link-commission.index');

        $this->actingAs($this->adminWith(['settings.view', 'product_link_commission.view']), 'admin');
        $urls = $this->sidebarUrls();
        $this->assertContains($distanceUrl, $urls);
        $this->assertSame(2, count(array_keys($urls, $commissionUrl, true)), 'Product link commission is listed under Users & Vendors and Finance');

        $this->actingAs($this->adminWith(['delivery.view']), 'admin');
        $urls = $this->sidebarUrls();
        $this->assertNotContains($distanceUrl, $urls);
        $this->assertNotContains($commissionUrl, $urls);
    }

    /**
     * @return list<string> hrefs of the sidebar links marked active on the page
     */
    private function activeSidebarHrefs(string $html): array
    {
        preg_match_all('/<a href="([^"]+)" class="nav-item[^"]*\bis-active\b/', $html, $matches);

        return array_map(fn (string $href) => html_entity_decode($href), $matches[1]);
    }

    public function test_only_shipping_zones_is_active_in_the_sidebar_on_the_rates_tab(): void
    {
        Country::factory()->create();

        $html = $this->actingAs($this->adminWith(['settings.view']), 'admin')
            ->get(route('admin.shipping-zones.index'))
            ->assertOk()
            ->getContent();

        $this->assertSame([route('admin.shipping-zones.index')], $this->activeSidebarHrefs($html));
    }

    public function test_only_distance_pricing_is_active_in_the_sidebar_on_the_distance_tab(): void
    {
        Country::factory()->create();

        $html = $this->actingAs($this->adminWith(['settings.view']), 'admin')
            ->get(route('admin.shipping-zones.index', ['tab' => 'distance']))
            ->assertOk()
            ->getContent();

        $this->assertSame([route('admin.shipping-zones.index', ['tab' => 'distance'])], $this->activeSidebarHrefs($html));
    }
}
