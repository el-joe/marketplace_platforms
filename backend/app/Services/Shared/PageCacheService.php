<?php

namespace App\Services\Shared;

use App\Models\AdminListing;
use App\Models\Country;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\VendorListing;
use Illuminate\Support\Facades\Cache;

/**
 * Central cache manager for the page builder + listing resolution system.
 *
 * Cache keys owned here:
 *   page_block:{id}:{countryId}                         PageRendererService per-block
 *   app_config_{countryId}                              AppConfigController home context
 *   browse_page_blocks:{type}:{countryId}:{nodeId}      BrowseService category pages
 *   product_delivery_options:{productId}:*              ProductDetailEnrichmentService
 */
class PageCacheService
{
    // ── Single block ──────────────────────────────────────────────────────────

    public function bustBlock(PageBlock $block): void
    {
        $countryId = $block->page?->country_id;

        if ($countryId) {
            Cache::forget("page_block:{$block->id}:{$countryId}");
        } else {
            Country::where('is_active', true)->pluck('id')
                ->each(fn ($cid) => Cache::forget("page_block:{$block->id}:{$cid}"));
        }
    }

    // ── All blocks on a page ──────────────────────────────────────────────────

    public function bustPage(Page $page): void
    {
        $countryId = $page->country_id;

        $page->blocks()->pluck('id')->each(function ($blockId) use ($countryId) {
            if ($countryId) {
                Cache::forget("page_block:{$blockId}:{$countryId}");
            }
        });

        if ($countryId) {
            Cache::forget("app_config_{$countryId}");
        }

        if ($page->page_type !== 'home' && $page->reference_id && $countryId) {
            Cache::forget("browse_page_blocks:{$page->page_type}:{$countryId}:{$page->reference_id}");
        }
    }

    // ── Listing-level cache bust ───────────────────────────────────────────────

    /**
     * Bust all customer API caches related to a VendorListing:
     *  - Buy-box resolution cache
     *  - Product delivery options (PDP enrichment)
     *  - Any page builder block that references this listing by ID
     *  - Cart recommendations best-sellers (country-scoped, accept short staleness)
     */
    public function bustVendorListing(VendorListing $listing): void
    {
        // Buy-box resolution
        app(\App\Services\CachedListingResolver::class)->bustVendorListing($listing);

        // PDP delivery options — all zones, same product+country
        $this->bustProductDeliveryOptions($listing->productVariant?->product_id, $listing->country_id);

        // Page builder blocks that explicitly reference this listing
        $this->bustBlocksReferencingListing('vendor_listing_id', $listing->id, $listing->country_id);
    }

    /**
     * Bust all customer API caches related to an AdminListing.
     */
    public function bustAdminListing(AdminListing $listing): void
    {
        app(\App\Services\CachedListingResolver::class)->bustAdminListing($listing);

        $this->bustProductDeliveryOptions($listing->productVariant?->product_id, $listing->country_id);

        $this->bustBlocksReferencingListing('admin_listing_id', $listing->id, $listing->country_id);
    }

    // ── Product delivery options ───────────────────────────────────────────────

    /**
     * Bust all cached delivery option payloads for a product in a country.
     * Key pattern: product_delivery_options:{productId}:{countryId}:{zoneId}:{listingId}
     * We can't enumerate all zone+listing combos, so we use a prefix scan via the
     * database cache driver (which stores keys in a table we can LIKE-query).
     */
    public function bustProductDeliveryOptions(?string $productId, ?string $countryId): void
    {
        if (! $productId || ! $countryId) {
            return;
        }

        $prefix = "product_delivery_options:{$productId}:{$countryId}:";

        // Database cache driver: keys are stored in the `cache` table
        try {
            \DB::table('cache')
                ->where('key', 'like', config('cache.prefix') . $prefix . '%')
                ->delete();
        } catch (\Throwable) {
            // Silently skip if cache driver doesn't support raw table access
        }
    }

    // ── Cart recommendations ───────────────────────────────────────────────────

    public function bustCartRecommendations(string $countryId): void
    {
        // Best-sellers are the only non-hash key we can bust deterministically
        foreach (['0', '1'] as $nawyNow) {
            $currencies = Country::where('id', $countryId)->pluck('currency_code');
            foreach ($currencies as $currency) {
                Cache::forget("cart_recs:best_sellers:{$countryId}:{$currency}:{$nawyNow}");
            }
        }
        // FBT and category-based recs use md5 hashes of cart contents — can't bust
        // deterministically; they self-heal within their 300s TTL.
    }

    // ── Navigation ────────────────────────────────────────────────────────────

    public function bustNavigation(string $countryId): void
    {
        Cache::forget("nav_tree:{$countryId}");
    }

    // ── Full country bust (all page + listing caches for one country) ─────────

    public function bustAllForCountry(string $countryId): void
    {
        Cache::forget("app_config_{$countryId}");
        Cache::forget("nav_tree:{$countryId}");
        Cache::forget("cart_recs:best_sellers:{$countryId}:*"); // pattern — may not work on DB driver

        PageBlock::whereHas('page', fn ($q) => $q->where('country_id', $countryId))
            ->pluck('id')
            ->each(fn ($id) => Cache::forget("page_block:{$id}:{$countryId}"));

        // Increment category version to invalidate category_tree and unified_nav
        \App\Services\Customer\CategoryService::flushCache();
        \App\Services\Customer\UnifiedCategoryService::flushCache();
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function bustBlocksReferencingListing(string $configKey, string $listingId, string $countryId): void
    {
        // Bust deal_of_day / product_row blocks that reference this listing by config key
        PageBlock::whereRaw("JSON_UNQUOTE(JSON_EXTRACT(config, '$.{$configKey}')) = ?", [$listingId])
            ->get()
            ->each(fn (PageBlock $block) => Cache::forget("page_block:{$block->id}:{$countryId}"));
    }
}
