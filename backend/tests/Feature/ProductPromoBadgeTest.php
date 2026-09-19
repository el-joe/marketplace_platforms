<?php

namespace Tests\Feature;

use App\Http\Resources\Customer\ProductDetailResource;
use App\Models\Product;
use App\Models\ProductPromoBadge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * docs/plans/dynamic-badges-and-classified-actions.md Task A: the
 * product_promo_badges table that drives the PDP/listing-card rotating
 * badge, additive to the existing shipping_badge/best_seller_badge fields.
 *
 * The flat `products.is_mega_deal` column this file originally also tested
 * was removed by docs/plans/mega-deal-page-builder-correction.md Task F —
 * "Mega deal" is now computed live from Page Builder `mega_deals` blocks
 * (see PageBuilderServiceMegaDealTest for that coverage) and exposed via
 * ProductDetailResource::$isMegaDeal, a plain public property the caller
 * sets, rather than a model attribute.
 */
class ProductPromoBadgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_promo_badges_relation_returns_only_active_badges_in_sort_order(): void
    {
        $product = Product::factory()->create();

        $inactive = ProductPromoBadge::factory()->for($product)->create([
            'label_en' => 'Inactive badge',
            'is_active' => false,
            'sort_order' => 0,
        ]);
        $second = ProductPromoBadge::factory()->for($product)->create([
            'label_en' => 'Second',
            'is_active' => true,
            'sort_order' => 2,
        ]);
        $first = ProductPromoBadge::factory()->for($product)->create([
            'label_en' => 'First',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $badges = $product->promoBadges()->get();

        $this->assertCount(2, $badges);
        $this->assertSame([$first->id, $second->id], $badges->pluck('id')->all());
        $this->assertFalse($badges->pluck('id')->contains($inactive->id));
    }

    public function test_product_detail_resource_exposes_promo_badges_additively_and_is_mega_deal_defaults_false(): void
    {
        $product = Product::factory()->create();
        ProductPromoBadge::factory()->for($product)->create([
            'label_en' => 'Free next-day delivery',
            'label_ar' => 'توصيل مجاني في اليوم التالي',
            'icon_key' => 'truck',
            'sort_order' => 0,
        ]);

        $product->load('promoBadges');

        $resource = new ProductDetailResource($product);
        $array = $resource->resolve(Request::create('/'));

        // isMegaDeal defaults to false unless a controller explicitly sets it
        // from PageBuilderService::isProductInActiveMegaDeal() — see
        // PageBuilderServiceMegaDealTest for the computed-true case.
        $this->assertFalse($array['is_mega_deal']);
        $this->assertCount(1, $array['promo_badges']);
        $this->assertSame('truck', $array['promo_badges'][0]['icon_key']);
        $this->assertSame('Free next-day delivery', $array['promo_badges'][0]['label']['en']);
        $this->assertSame('توصيل مجاني في اليوم التالي', $array['promo_badges'][0]['label']['ar']);

        // Purely additive: existing fields untouched.
        $this->assertArrayHasKey('has_variants', $array);
        $this->assertArrayHasKey('id', $array);
    }

    public function test_product_detail_resource_promo_badges_absent_when_relation_not_loaded(): void
    {
        $product = Product::factory()->create();

        $resource = new ProductDetailResource($product);
        $array = $resource->resolve(Request::create('/'));

        $this->assertArrayNotHasKey('promo_badges', $array);
        $this->assertFalse($array['is_mega_deal']);
    }

    public function test_listing_level_badges_are_excluded_from_product_level_relation(): void
    {
        $listing = \Tests\Support\MarketplaceScenario::make()->build()->vendorListingFbp;
        $product = $listing->productVariant->product;

        $productBadge = ProductPromoBadge::factory()->for($product)->create(['is_active' => true]);
        $listingBadge = ProductPromoBadge::factory()->for($product)->create([
            'vendor_listing_id' => $listing->id,
            'is_active' => true,
        ]);

        $this->assertSame([$productBadge->id], $product->promoBadges()->pluck('id')->all());
        $this->assertSame([$listingBadge->id], $listing->promoBadges()->pluck('id')->all());
    }

    public function test_sync_service_scopes_badges_to_vendor_admin_and_marketer_listings(): void
    {
        $sc = \Tests\Support\MarketplaceScenario::make()->build();
        $productId = $sc->vendorListingFbp->productVariant->product_id;
        $svc = app(\App\Services\Shared\PromoBadgeSyncService::class);
        $row = fn (string $en) => [['label_en' => $en, 'label_ar' => $en . ' ar', 'icon_key' => 'Truck', 'is_active' => true]];

        $svc->sync($productId, null, null, $row('product'));
        $svc->sync($productId, 'vendor_listing_id', $sc->vendorListingFbp->id, $row('vendor'));
        $svc->sync($productId, 'admin_listing_id', $sc->adminListing->id, $row('admin'));
        $svc->sync($productId, 'marketer_listing_id', $sc->marketerListing->id, $row('marketer'));

        $this->assertSame(['product'], ProductPromoBadge::productLevel()->pluck('label_en')->all());
        $this->assertSame(['vendor'], $sc->vendorListingFbp->promoBadges()->pluck('label_en')->all());
        $this->assertSame(['admin'], $sc->adminListing->promoBadges()->pluck('label_en')->all());
        $this->assertSame(['marketer'], $sc->marketerListing->promoBadges()->pluck('label_en')->all());

        // Re-syncing one owner with an empty list clears only that owner's rows.
        $svc->sync($productId, 'admin_listing_id', $sc->adminListing->id, []);
        $this->assertSame(0, $sc->adminListing->promoBadges()->count());
        $this->assertSame(4 - 1, ProductPromoBadge::count());
    }

    public function test_check_constraint_rejects_two_owners_and_accepts_zero_or_one(): void
    {
        $sc = \Tests\Support\MarketplaceScenario::make()->build();
        $productId = $sc->vendorListingFbp->productVariant->product_id;

        ProductPromoBadge::factory()->create(['product_id' => $productId]);
        ProductPromoBadge::factory()->create(['product_id' => $productId, 'vendor_listing_id' => $sc->vendorListingFbp->id]);
        $this->assertSame(2, ProductPromoBadge::count());

        $this->expectException(\Illuminate\Database\QueryException::class);
        ProductPromoBadge::factory()->create([
            'product_id' => $productId,
            'vendor_listing_id' => $sc->vendorListingFbp->id,
            'admin_listing_id' => $sc->adminListing->id,
        ]);
    }

    public function test_icon_key_whitelist_rejects_unknown_and_accepts_known(): void
    {
        $v = fn (string $icon) => \Illuminate\Support\Facades\Validator::make(
            ['promo_badges' => [['label_en' => 'a', 'label_ar' => 'b', 'icon_key' => $icon]]],
            \App\Services\Shared\PromoBadgeSyncService::rules()
        );

        $this->assertTrue($v('Truck')->passes());
        $this->assertTrue($v('NotAnIcon')->fails());
        $this->assertArrayHasKey('promo_badges.0.icon_key', $v('NotAnIcon')->errors()->toArray());
    }

    public function test_sync_busts_storefront_caches_for_listing_and_product_level(): void
    {
        $sc = \Tests\Support\MarketplaceScenario::make()->build();
        $productId = $sc->vendorListingFbp->productVariant->product_id;
        $row = [['label_en' => 'x', 'label_ar' => 'y', 'icon_key' => 'Truck', 'is_active' => true]];

        $spy = \Mockery::mock(\App\Services\Shared\PageCacheService::class)->shouldIgnoreMissing();
        $spy->shouldReceive('bustVendorListing')->atLeast()->once();
        $this->app->instance(\App\Services\Shared\PageCacheService::class, $spy);

        $svc = app(\App\Services\Shared\PromoBadgeSyncService::class);
        $svc->sync($productId, 'vendor_listing_id', $sc->vendorListingFbp->id, $row);
        $svc->sync($productId, null, null, $row);
    }
}
