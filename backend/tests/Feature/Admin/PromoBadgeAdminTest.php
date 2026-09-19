<?php

namespace Tests\Feature\Admin;

use App\Models\Activity;
use App\Models\Admin;
use App\Models\AdminListing;
use App\Models\Country;
use App\Models\Product;
use App\Models\ProductPromoBadge;
use App\Models\ProductVariant;
use App\Services\Shared\PageCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PromoBadgeAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $perms): Admin
    {
        Permission::firstOrCreate(['name' => 'vendors.assigned_only', 'guard_name' => 'admin']);
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'admin']);
        }
        $admin = Admin::factory()->create();
        $admin->givePermissionTo($perms);

        return $admin;
    }

    private function badge(array $o = []): array
    {
        return array_merge([
            'label_en' => 'Free delivery', 'label_ar' => 'توصيل مجاني',
            'icon_key' => 'Truck', 'color_hex' => '#112233', 'text_color_hex' => '#FFFFFF', 'is_active' => '1',
        ], $o);
    }

    private function productPayload(array $extra = []): array
    {
        $category = \App\Models\Category::factory()->create();

        return array_merge([
            'name_en' => 'Widget', 'name_ar' => 'ودجت', 'category_id' => $category->id, 'status' => 'draft',
        ], $extra);
    }

    public function test_create_product_with_badges(): void
    {
        $admin = $this->admin(['products.view', 'products.create', 'products.edit']);

        $res = $this->actingAs($admin, 'admin')->postJson(route('admin.products.store'), $this->productPayload([
            'promo_badges' => [$this->badge()],
        ]));

        $res->assertOk();
        $product = Product::where('name_en', 'Widget')->firstOrFail();
        $this->assertSame(1, ProductPromoBadge::where('product_id', $product->id)->productLevel()->count());
        $this->assertTrue(Activity::where('event', 'promo_badges_changed')->where('subject_id', $product->id)->exists());
    }

    public function test_update_badges_logs_before_and_after(): void
    {
        $admin = $this->admin(['products.view', 'products.edit']);
        $product = Product::factory()->create();
        ProductPromoBadge::factory()->for($product)->create(['label_en' => 'Old', 'label_ar' => 'قديم']);

        $res = $this->actingAs($admin, 'admin')->putJson(route('admin.products.update', $product->id), [
            'name_en' => $product->name_en, 'name_ar' => $product->name_ar,
            'category_id' => $product->category_id, 'status' => 'draft',
            'promo_badges' => [$this->badge(['label_en' => 'New'])],
        ]);

        $res->assertOk();
        $this->assertSame(['New'], ProductPromoBadge::where('product_id', $product->id)->pluck('label_en')->all());
        $log = Activity::where('event', 'promo_badges_changed')->firstOrFail();
        $this->assertSame('Old', $log->properties['before'][0]['label_en']);
        $this->assertSame('New', $log->properties['after'][0]['label_en']);
        $this->assertSame($admin->id, $log->causer_id);
    }

    public function test_icon_whitelist_rejected_on_store_and_validate_update(): void
    {
        $admin = $this->admin(['products.view', 'products.edit', 'products.create']);
        $product = Product::factory()->create();
        $bad = [$this->badge(['icon_key' => 'NotARealIcon'])];

        $this->actingAs($admin, 'admin')->postJson(route('admin.products.store'), $this->productPayload(['promo_badges' => $bad]))
            ->assertStatus(422)->assertJsonValidationErrors('promo_badges.0.icon_key');

        $this->actingAs($admin, 'admin')->postJson(route('admin.products.validate-update', $product->id), [
            'name_en' => 'x', 'name_ar' => 'x', 'category_id' => $product->category_id, 'status' => 'draft',
            'promo_badges' => $bad,
        ])->assertStatus(422)->assertJsonValidationErrors('promo_badges.0.icon_key');
    }

    public function test_max_badges_enforced(): void
    {
        $admin = $this->admin(['products.view', 'products.create']);
        $rows = array_fill(0, config('promo_badges.max_per_owner') + 1, $this->badge());

        $this->actingAs($admin, 'admin')->postJson(route('admin.products.store'), $this->productPayload(['promo_badges' => $rows]))
            ->assertStatus(422)->assertJsonValidationErrors('promo_badges');
    }

    public function test_permission_required(): void
    {
        $viewer = $this->admin(['products.view']);
        $product = Product::factory()->create();

        $this->actingAs($viewer, 'admin')->putJson(route('admin.products.update', $product->id), [
            'name_en' => 'x', 'name_ar' => 'x', 'category_id' => $product->category_id, 'status' => 'draft',
            'promo_badges' => [$this->badge()],
        ])->assertForbidden();
        $this->assertSame(0, ProductPromoBadge::where('product_id', $product->id)->count());
    }

    private function adminListing(): AdminListing
    {
        $variant = ProductVariant::factory()->for(Product::factory()->create(), 'product')->create();
        $country = Country::factory()->create();

        return AdminListing::forceCreate([
            'product_variant_id' => $variant->id, 'country_id' => $country->id, 'price' => 1000,
            'currency' => 'EGP', 'created_by_admin_id' => Admin::factory()->create()->id, 'condition' => 'new', 'low_stock_threshold' => 1, 'status' => 'active',
        ]);
    }

    public function test_admin_listing_badge_save_and_log(): void
    {
        $admin = $this->admin(['admin_listings.view', 'admin_listings.edit']);
        $listing = $this->adminListing();

        $this->actingAs($admin, 'admin')->put(route('admin.admin-listings.promo-badges.update', $listing), [
            'promo_badges' => [$this->badge()],
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, ProductPromoBadge::where('admin_listing_id', $listing->id)->count());
        $this->assertTrue(Activity::where('event', 'promo_badges_changed')->where('subject_id', $listing->id)->exists());
    }

    public function test_clear_cache_calls_existing_bust_method(): void
    {
        $admin = $this->admin(['admin_listings.view', 'admin_listings.edit']);
        $listing = $this->adminListing();

        $this->mock(PageCacheService::class)->shouldReceive('bustAdminListing')->once();

        $this->actingAs($admin, 'admin')->postJson(route('admin.admin-listings.clear-cache', $listing))
            ->assertOk()->assertJson(['success' => true]);
    }
}
