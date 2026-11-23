<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\ProductLinkCommissionRule;
use App\Models\VendorListing;
use App\Models\WarehouseInventory;
use App\Services\ProductLink\ProductShareLinkService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\CountryContractFixtures;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/** WS12 #24: the admin rule page and the vendor's "My product link / QR" panel. */
class ProductLinkCommissionRuleAdminTest extends TestCase
{
    use CountryContractFixtures;
    use RefreshDatabase;

    private MarketplaceScenario $scenario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);
        foreach (['vendors.assigned_only', 'product_link_commission.view', 'product_link_commission.manage'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'admin']);
        }
        $this->scenario = MarketplaceScenario::make()->build();
        $this->scenario->country->update(['is_active' => true]);
    }

    private function admin(bool $canManage = true): Admin
    {
        $admin = Admin::factory()->create();
        $admin->givePermissionTo($canManage ? ['product_link_commission.view', 'product_link_commission.manage'] : ['product_link_commission.view']);

        return $admin;
    }

    public function test_a_country_rule_is_created_then_updated(): void
    {
        $url = route('admin.product-link-commission.update', $this->scenario->country);

        $this->actingAs($this->admin(), 'admin')->put($url, [
            'is_active' => '1', 'fulfillment_models' => ['fbm', 'fbn'], 'discount_type' => 'both',
            'discount_percent' => '25', 'discount_fixed' => '1.5', 'attribution_window_days' => '10',
        ])->assertRedirect(route('admin.product-link-commission.index'))->assertSessionHasNoErrors();

        $rule = ProductLinkCommissionRule::where('country_id', $this->scenario->country->id)->sole();
        $this->assertSame(['fbm', 'fbn'], $rule->fulfillment_models);
        $this->assertEquals(25, $rule->discount_percent);
        $this->assertEquals(1.5, $rule->discount_fixed);
        $this->assertSame(10, $rule->attribution_window_days);

        // Switching to "zero" clears the amounts the type does not use.
        $this->actingAs($this->admin(), 'admin')->put($url, [
            'is_active' => '1', 'fulfillment_models' => ['fbn'], 'discount_type' => 'zero',
            'discount_percent' => '25', 'discount_fixed' => '1.5', 'attribution_window_days' => '7',
        ])->assertSessionHasNoErrors();

        $rule->refresh();
        $this->assertSame('zero', $rule->discount_type);
        $this->assertEquals(0, $rule->discount_percent);
        $this->assertEquals(0, $rule->discount_fixed);
        $this->assertSame(1, ProductLinkCommissionRule::count());

        $this->actingAs($this->admin(), 'admin')->get(route('admin.product-link-commission.index'))
            ->assertOk()
            ->assertSee(__('product_link.describe.zero'));
    }

    public function test_the_amount_of_the_chosen_type_and_a_sane_window_are_required(): void
    {
        $url = route('admin.product-link-commission.update', $this->scenario->country);

        $this->actingAs($this->admin(), 'admin')->put($url, [
            'is_active' => '1', 'fulfillment_models' => ['fbm'], 'discount_type' => 'percent',
            'discount_percent' => '0', 'attribution_window_days' => '7',
        ])->assertSessionHasErrors('discount_percent');

        $this->actingAs($this->admin(), 'admin')->put($url, [
            'is_active' => '1', 'fulfillment_models' => ['classifieds'], 'discount_type' => 'fixed',
            'discount_fixed' => '2', 'attribution_window_days' => '365',
        ])->assertSessionHasErrors(['fulfillment_models.0', 'attribution_window_days']);

        $this->assertSame(0, ProductLinkCommissionRule::count());
    }

    public function test_viewing_does_not_allow_saving(): void
    {
        $this->actingAs($this->admin(canManage: false), 'admin')
            ->get(route('admin.product-link-commission.index'))
            ->assertOk();

        $this->actingAs($this->admin(canManage: false), 'admin')
            ->put(route('admin.product-link-commission.update', $this->scenario->country), [
                'is_active' => '1', 'fulfillment_models' => ['fbm'], 'discount_type' => 'zero', 'attribution_window_days' => '7',
            ])
            ->assertForbidden();
    }

    public function test_the_vendor_sees_a_signed_link_and_qr_code_for_its_listing(): void
    {
        $vendor = $this->vendorIn($this->scenario->country, 'LinkSeller');
        $owner = $this->ownerFor($vendor);
        foreach (['listings.view', 'marketer_campaigns.create'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'vendor']);
        }
        $owner->givePermissionTo('listings.view');

        $listing = $this->scenario->vendorListingFbp->replicate();
        $listing->id = (string) Str::uuid();
        $listing->vendor_id = $vendor->id;
        $listing->save();
        WarehouseInventory::create(['vendor_listing_id' => $listing->id, 'warehouse_id' => $this->scenario->vendorWarehouse->id, 'quantity_on_hand' => 1, 'quantity_reserved' => 0]);

        $links = app(ProductShareLinkService::class);

        $this->actingAs($owner, 'vendor')
            ->get(route('partner.listings.show', $listing))
            ->assertOk()
            ->assertSee(__('product_link.partner.button'))
            ->assertSee($links->urlFor($listing), false)
            ->assertSee('data:image/svg+xml', false);

        $this->assertSame($listing->id, $links->resolve($links->tokenFor($listing))?->id);
        // The token of one listing does not open another vendor's listing.
        $other = $links->tokenFor($this->scenario->vendorListingFbp);
        $this->assertNull($links->resolve(substr($links->tokenFor($listing), 0, 32).substr($other, 32)));
        $this->assertInstanceOf(VendorListing::class, $links->resolve($other));
    }
}
