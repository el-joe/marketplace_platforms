<?php

namespace App\Services\Customer;

use App\Enums\VendorListingStatus;
use App\Http\Resources\Customer\FlashSaleItemResource;
use App\Http\Resources\Customer\ProductListResource;
use App\Jobs\FlushBlockImpressionJob;
use App\Jobs\LogAbImpressionJob;
use App\Models\AbTest;
use App\Models\AdImageItem;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Country;
use App\Models\Customer;
use App\Models\FlashSale;
use App\Models\FlashSaleSubmission;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\PageBlockCategory;
use App\Models\PageBlockProduct;
use App\Models\PageBlockSeller;
use App\Models\PageSection;
use App\Models\ProductCountrySetting;
use App\Models\ProductVariant;
use App\Models\VendorListing;
use App\Enums\VendorGlobalStatus;
use App\Support\Bilingual;
use App\Support\SafeCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Renders customer-facing pages by resolving a Page, its PageSections, and the
 * PageBlocks within them, applying visibility/audience/country/A-B filtering,
 * then hydrating each surviving block's data per its block_type.
 *
 * This is the highest read-volume endpoint in the system — every block's
 * hydrated payload is cached individually (see hydrateBlockCached()); the
 * assembled page response itself is NOT cached because audience/A-B state is
 * resolved per-visitor before hydration even starts.
 */
class PageRendererService
{
    /**
     * Known block_type strings. 16 distinct types total, counting
     * countdown_deal/countdown_timer and ad_images_2col/ad_images_4col as
     * separate types (both pairs share a hydrator method).
     */
    private const BLOCK_TYPES = [
        'hero_slider', 'countdown_deal', 'countdown_timer', 'video_banner',
        'product_row', 'flash_sale', 'deal_of_day', 'ad_images_2col',
        'ad_images_4col', 'full_banner', 'category_pills',
        'brand_strip', 'search_trends', 'text_block', 'divider', 'newsletter_signup',
        'mega_deals', 'promo_tiles', 'image_slider',
        'ad_images_3col', 'sponsored_grid', 'app_download_banner',
    ];

    public function __construct(
        private readonly ProductQueryService $productQuery,
        private readonly \App\Services\Customer\ListingQueryService $listingQuery,
    ) {
    }

    public function render(
        string $type,
        ?string $slug,
        Country $country,
        ?Customer $customer,
        string $sessionId,
    ): array {
        $now = now();

        $page = Page::where('page_type', $type)
            ->where('status', 'published')
            ->where('is_default', true)
            ->where(fn($q) => $q->whereNull('country_id')->orWhere('country_id', $country->id))
            ->when($slug !== null, fn($q) => $q->where('slug', $slug))
            ->where(fn($q) => $q->whereNull('publish_at')->orWhere('publish_at', '<=', $now))
            ->where(fn($q) => $q->whereNull('unpublish_at')->orWhere('unpublish_at', '>', $now))
            ->orderByRaw('CASE WHEN country_id IS NOT NULL THEN 0 ELSE 1 END')
            ->first();

        if (!$page) {
            return [];
        }

        return $this->renderPage($page, $country, $customer, $sessionId);
    }

    /**
     * Renders an already-resolved Page (e.g. one looked up by id rather than
     * by page_type/slug, as with app-context home pages).
     *
     * $appContextKey scopes block visibility to a specific app context (e.g.
     * 'nawy_now') — blocks with a different app_context_key are dropped, while
     * blocks with a NULL app_context_key are always shown. Pass null (default)
     * to skip context filtering entirely, preserving existing callers' behavior.
     */
    public function renderPage(Page $page, Country $country, ?Customer $customer, string $sessionId, ?string $appContextKey = null): array
    {
        $identityKey = $customer ? 'c:' . $customer->id : 's:' . $sessionId;

        // Resolve A/B variants for all running tests on this page, ASYNC log the
        // impression so it never blocks the response.
        $runningTests = AbTest::where('page_id', $page->id)
            ->where('status', 'running')
            ->get();

        $chosenVariants = [];
        foreach ($runningTests as $test) {
            $variant = $this->resolveAbVariant($test, $identityKey);
            $chosenVariants[$test->id] = $variant;
            dispatch(new LogAbImpressionJob($test->id, $variant));
        }

        return $this->assemble($page, $country, $customer, $chosenVariants, $appContextKey);
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function assemble(Page $page, Country $country, ?Customer $customer, array $chosenVariants, ?string $appContextKey = null): array
    {
        $sections = PageSection::where('page_id', $page->id)
            ->where('is_visible', true)
            ->orderBy('position')
            ->get();

        $allBlocks = PageBlock::where('page_id', $page->id)
            ->orderBy('position')
            ->get();

        $resolvedBlocks = $this->filterBlocks($allBlocks, $country, $customer, $chosenVariants, $appContextKey);

        $sectionsData = [];

        foreach ($sections as $section) {
            $sectionBlocks = $resolvedBlocks->where('section_id', $section->id)->values();
            $hydrated = $this->hydrateBlocks($sectionBlocks, $page, $country, $customer);

            if ($hydrated->isNotEmpty()) {
                $sectionsData[] = [
                    'id'                   => $section->id,
                    'name'                 => $section->name,
                    'position'             => $section->position,
                    'layout'               => $section->layout ?? 'stack',
                    'columns_config'       => is_string($section->columns_config)
                        ? json_decode($section->columns_config, true)
                        : $section->columns_config,
                    'background_color'     => $section->background_color,
                    'background_image_url' => $section->background_image_url,
                    'padding_top'          => (int) $section->padding_top,
                    'padding_bottom'       => (int) $section->padding_bottom,
                    'max_width'            => $section->max_width,
                    'blocks'               => $hydrated->all(),
                ];
            }
        }

        // Blocks not assigned to any section go in a virtual section.
        $unsectioned = $resolvedBlocks->whereNull('section_id')->values();
        if ($unsectioned->isNotEmpty()) {
            $hydrated = $this->hydrateBlocks($unsectioned, $page, $country, $customer);
            if ($hydrated->isNotEmpty()) {
                $sectionsData[] = [
                    'id'                   => null,
                    'name'                 => null,
                    'position'             => 0,
                    'layout'               => 'stack',
                    'columns_config'       => null,
                    'background_color'     => null,
                    'background_image_url' => null,
                    'padding_top'          => 0,
                    'padding_bottom'       => 0,
                    'max_width'            => null,
                    'blocks'               => $hydrated->all(),
                ];
            }
        }

        return [
            'page' => [
                'type' => $page->page_type,
                'slug' => $page->slug,
                'version' => $page->version,
            ],
            'sections' => $sectionsData,
        ];
    }

    /**
     * Apply all block-level filtering rules BEFORE any hydration happens.
     * A block is skipped (removed from the collection) if any rule matches.
     */
    private function filterBlocks(Collection $blocks, Country $country, ?Customer $customer, array $chosenVariants, ?string $appContextKey = null): Collection
    {
        $now = now();

        return $blocks->filter(function (PageBlock $block) use ($now, $country, $customer, $chosenVariants, $appContextKey) {
            if (!$block->is_visible) {
                return false;
            }
            if ($appContextKey !== null && $block->app_context_key !== null && $block->app_context_key !== $appContextKey) {
                return false;
            }
            if ($block->visible_from !== null && $block->visible_from->gt($now)) {
                return false;
            }
            if ($block->visible_until !== null && $block->visible_until->lt($now)) {
                return false;
            }
            if ($block->country_override !== null && $block->country_override !== $country->id) {
                return false;
            }
            // NOTE: the page_blocks.audience column uses 'guest' (singular), not the
            // spec's 'guests'. Semantics preserved: guest-only blocks hidden from
            // authenticated customers and vice versa.
            if ($block->audience === 'logged_in' && !$customer) {
                return false;
            }
            if ($block->audience === 'guest' && $customer) {
                return false;
            }
            if ($block->ab_test_id !== null) {
                if (!isset($chosenVariants[$block->ab_test_id])) {
                    return false;
                }
                if ($block->ab_variant !== $chosenVariants[$block->ab_test_id]) {
                    return false;
                }
            }

            return true;
        })->values();
    }

    private function hydrateBlocks(Collection $blocks, Page $page, Country $country, ?Customer $customer): Collection
    {
        return $blocks
            ->map(function (PageBlock $block) use ($page, $country, $customer) {
                $cached = $this->hydrateBlockCached($block, $country, $customer);

                if ($cached === null) {
                    return null;
                }

                // Fire-and-forget impression tracking — never blocks the response.
                dispatch(new FlushBlockImpressionJob($block->id, $page->id, $country->id));

                return [
                    'id'                => $block->id,
                    'type'              => $block->block_type,
                    'position'          => $block->position,
                    'device_target'     => $block->device_target,
                    'audience'          => $block->audience,
                    'cache_ttl_seconds' => $block->cache_ttl_seconds,
                    'background_color'  => ($block->config['background_color'] ?? null),
                    'data'              => $cached,
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * Per-block cache wrapper. Cache key is scoped to (block, country) only —
     * none of the 17 hydrators below use $customer as a query filter, so the
     * cached payload is safe to share across every visitor who reaches this
     * block (audience/A-B gating already happened in filterBlocks(), before
     * we ever get here).
     */
    private function hydrateBlockCached(PageBlock $block, Country $country, ?Customer $customer): ?array
    {
        $ttl = max(1, (int) $block->cache_ttl_seconds);

        return SafeCache::remember(
            "page_block:{$block->id}:{$country->id}",
            $ttl,
            fn() => $this->hydrateBlock($block, $country, $customer),
        );
    }

    public function resolveAbVariant(AbTest $test, string $identityKey): string
    {
        $bucket = (crc32($identityKey . ':' . $test->id) & 0x7FFFFFFF) % 100;
        return $bucket < $test->traffic_split_pct ? 'b' : 'a';
    }

    // ─── Dispatcher ─────────────────────────────────────────────────────────

    public function hydrateBlock(PageBlock $block, Country $country, ?Customer $customer): ?array
    {
        return match ($block->block_type) {
            'hero_slider' => $this->hydrateHeroSlider($block),
            'countdown_deal', 'countdown_timer' => $this->hydrateCountdown($block),
            'video_banner' => $this->hydrateVideoBanner($block),
            'product_row' => $this->hydrateProductRow($block, $country),
            'flash_sale' => $this->hydrateFlashSale($block, $country),
            'deal_of_day' => $this->hydrateDealOfDay($block, $country),
            'ad_images_2col' => $this->hydrateAdImages($block, 2),
            'ad_images_4col' => $this->hydrateAdImages($block, 4),
            'full_banner' => $this->hydrateFullBanner($block),
            'category_pills' => $this->hydrateCategoryPills($block, $country),
            'brand_strip' => $this->hydrateBrandStrip($block),
            'search_trends' => $this->hydrateSearchTrends($block, $country),
            'text_block' => $this->hydrateTextBlock($block),
            'divider' => $this->hydrateDivider($block),
            'newsletter_signup' => $this->hydrateNewsletterSignup($block),
            'mega_deals' => $this->hydrateMegaDeals($block, $country),
            'promo_tiles' => $this->hydratePromoTiles($block),
            'image_slider' => $this->hydrateImageSlider($block),
            'ad_images_3col' => $this->hydrateAdImages($block, 3),
            'sponsored_grid' => $this->hydrateSponsoredGrid($block, $country),
            'app_download_banner' => $this->hydrateAppDownloadBanner($block),
            default => $block->config ?? [],
        };
    }

    // ─── 1. hero_slider ─────────────────────────────────────────────────────

    private function hydrateHeroSlider(PageBlock $block): array
    {
        $now = now();

        $slides = $block->slides()
            ->where('is_active', true)
            ->where(fn($q) => $q->whereNull('visible_from')->orWhere('visible_from', '<=', $now))
            ->where(fn($q) => $q->whereNull('visible_until')->orWhere('visible_until', '>=', $now))
            ->orderBy('position')
            ->get();

        $cfg = $block->config ?? [];

        return [
            'config' => [
                'height_desktop' => $cfg['height_desktop'] ?? null,
                'autoplay_seconds' => $cfg['autoplay_seconds'] ?? null,
                'show_dots' => $cfg['show_dots'] ?? true,
                'show_arrows' => $cfg['show_arrows'] ?? true,
                'loop' => $cfg['loop'] ?? true,
                'transition' => $cfg['transition'] ?? 'slide',
                'is_announcement' => (bool) ($cfg['is_announcement'] ?? false),
            ],
            // desktop_file_id/mobile_file_id are intentionally NEVER exposed —
            // only their resolved URLs (see SliderSlide::getDesktopUrlAttribute()).
            'slides' => $slides->map(fn($s) => [
                'desktop_image_url' => $s->desktop_url,
                'mobile_image_url' => $s->mobile_url,
                'title' => Bilingual::pair($s, 'title'),
                'subtitle' => Bilingual::pair($s, 'subtitle'),
                'cta_label' => Bilingual::pair($s, 'cta_label'),
                'cta_url' => $s->cta_url,
                'cta_open_new_tab' => (bool) $s->cta_open_new_tab,
                'text_color' => $s->text_color,
                'text_position' => $s->text_position,
                'overlay_opacity' => (float) $s->overlay_opacity,
                'link_type' => $s->link_type,
                'link_reference_id' => $s->link_reference_id,
                'is_paid' => (bool) $s->is_paid,
            ])->all(),
        ];
    }

    // ─── 2. countdown_deal / countdown_timer ───────────────────────────────

    private function hydrateCountdown(PageBlock $block): ?array
    {
        $cfg = $block->config ?? [];
        $endsAt = isset($cfg['ends_at']) ? \Illuminate\Support\Carbon::parse($cfg['ends_at']) : null;

        if ($endsAt === null || $endsAt->lt(now())) {
            return null;
        }

        return [
            'ends_at' => $endsAt->toIso8601String(),
            'seconds_remaining' => max(0, now()->diffInSeconds($endsAt, false)),
            'title' => $cfg['title'] ?? null,
            'colors' => $cfg['colors'] ?? null,
        ];
    }

    // ─── 3. video_banner ────────────────────────────────────────────────────

    private function hydrateVideoBanner(PageBlock $block): array
    {
        $cfg = $block->config ?? [];

        return [
            'video_url' => $cfg['video_url'] ?? null,
            'poster_url' => $cfg['poster_url'] ?? null,
            'autoplay' => (bool) ($cfg['autoplay'] ?? false),
            'muted' => (bool) ($cfg['muted'] ?? true),
        ];
    }

    // ─── 4. product_row ─────────────────────────────────────────────────────

    private function hydrateProductRow(PageBlock $block, Country $country): array
    {
        $cfg = $block->config ?? [];
        $source = $cfg['source'] ?? 'manual';
        $maxProducts = (int) ($cfg['max_products'] ?? 10);

        $products = match ($source) {
            'manual' => $this->productRowManual($block, $country),
            'flash_sale', 'flash_sale_products' => $this->productRowFlashSale($cfg, $maxProducts, $country),
            default => $this->productRowQueried($source, $cfg, $country, $maxProducts),
        };

        return [
            'title' => Bilingual::pairFromKeys($cfg, 'title_ar', 'title_en'),
            'source' => $source,
            'items_per_row' => $cfg['items_per_row'] ?? 4,
            'rows_count' => (int) ($cfg['rows_count'] ?? 1),
            'card_style' => $cfg['card_style'] ?? 'normal',
            'scrollable_row' => (bool) ($cfg['scrollable_row'] ?? true),
            'show_view_all' => (bool) ($cfg['show_view_all'] ?? true),
            'show_ratings' => (bool) ($cfg['show_ratings'] ?? true),
            'show_discount_badge' => (bool) ($cfg['show_discount_badge'] ?? true),
            // CRITICAL: admin_share_pct / product_value from
            // marketer_secret_promotions must NEVER be surfaced here, even for the
            // flash_sale_products source — those columns are never selected below.
            'products' => $products,
        ];
    }

    private function productRowManual(PageBlock $block, Country $country): array
    {
        $blockProducts = PageBlockProduct::where('page_block_id', $block->id)
            ->with(['productVariant.product.images', 'productVariant.product.category', 'productVariant.images'])
            ->orderBy('position')
            ->get();

        if ($blockProducts->isEmpty()) {
            return [];
        }

        $productIds = $blockProducts
            ->map(fn ($bp) => optional($bp->productVariant)->product_id)
            ->filter()->unique()->all();

        // Filter products unavailable in this country
        $unavailableIds = ProductCountrySetting::whereIn('product_id', $productIds)
            ->where('country_id', $country->id)
            ->where('is_available', false)
            ->pluck('product_id')->flip()->all();

        $variantIds = $blockProducts->pluck('product_variant_id')->filter()->all();

        // ── Resolve buy-box per variant: admin first, vendor fallback ─────────
        $adminByVariant = \App\Models\AdminListing::query()
            ->whereIn('product_variant_id', $variantIds)
            ->where('country_id', $country->id)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->with([
                'primaryShippingMethod:id,badge_label_en,badge_label_ar,badge_color_hex,badge_text_color_hex,min_delivery_days,max_delivery_days',
                'productVariant:id,sku,slug,variant_name',
                'productVariant.images',
            ])
            ->orderBy('price')
            ->get()
            ->keyBy('product_variant_id');

        $vendorByVariant = VendorListing::query()
            ->whereIn('product_variant_id', $variantIds)
            ->where('country_id', $country->id)
            ->where('status', VendorListingStatus::Active->value)
            ->whereHas('vendor', fn ($q) => $q->where('global_status', VendorGlobalStatus::Active->value))
            ->with([
                'vendor:id,store_name,store_rating_avg',
                'primaryShippingMethod:id,badge_label_en,badge_label_ar,badge_color_hex,badge_text_color_hex,min_delivery_days,max_delivery_days',
                'productVariant:id,sku,slug,variant_name',
                'productVariant.images',
            ])
            ->orderByRaw('score IS NULL, score DESC')
            ->orderBy('price')
            ->get()
            ->keyBy('product_variant_id');

        return $blockProducts
            ->filter(fn ($bp) =>
                $bp->productVariant
                && $bp->productVariant->product_id
                && !isset($unavailableIds[$bp->productVariant->product_id])
            )
            ->map(function ($bp) use ($adminByVariant, $vendorByVariant, $country) {
                $variantId = $bp->product_variant_id;
                $product   = $bp->productVariant->product;

                // Admin wins; fall back to vendor
                $isAdmin = isset($adminByVariant[$variantId]);
                $listing = $isAdmin
                    ? $adminByVariant[$variantId]
                    : ($vendorByVariant[$variantId] ?? null);

                if (! $listing) {
                    return null; // No active listing in this country — skip card
                }

                return $this->listingQuery->toMixedCardShape($listing, $product, $country);
            })
            ->filter()
            ->values()
            ->all();
    }

    private function productRowQueried(string $source, array $cfg, Country $country, int $maxProducts): array
    {
        $sort = match ($source) {
            'best_sellers' => 'best_selling',
            'new_arrivals' => 'newest',
            'top_rated' => 'rating',
            'trending' => 'best_selling', // approximated by total_sold until a dedicated view_count sort is available
            'category' => 'best_selling', // already scoped to category via $cfg['category_id']
            'personalized' => 'rating', // no personalization engine yet; best approximate
            'featured' => 'relevance',
            default => 'relevance',
        };

        // Include the selected category AND all its subcategories (same as BrowseController).
        // Without this, products in child categories never appear in the block.
        if (!empty($cfg['category_id'])) {
            $rootCategory = \App\Models\Category::find($cfg['category_id']);
            $categoryIds = $rootCategory
                ? app(\App\Services\Customer\CategoryService::class)->getDescendantIds($rootCategory)
                : [$cfg['category_id']];
        } else {
            $categoryIds = null;
        }

        $paginator = $this->productQuery->paginate(
            $country,
            ['sort' => $sort],
            $maxProducts,
            $categoryIds,
        );

        return $this->productQuery->buildProductsPayload($paginator, $country, 1, 'page_block')['items'];
    }

    private function productRowFlashSale(array $cfg, int $maxProducts, Country $country): array
    {
        if (empty($cfg['flash_sale_id'])) {
            return [];
        }

        $submissions = FlashSaleSubmission::where('flash_sale_id', $cfg['flash_sale_id'])
            ->whereIn('status', ['approved', 'live'])
            ->with([
                'vendorListing.vendor:id,store_name,store_rating_avg',
                'vendorListing.primaryShippingMethod:id,badge_label_en,badge_label_ar,badge_color_hex,badge_text_color_hex,min_delivery_days,max_delivery_days',
                'vendorListing.productVariant.product.images',
                'vendorListing.productVariant.images',
            ])
            ->orderBy('created_at')
            ->limit($maxProducts)
            ->get();

        return $submissions
            ->filter(fn ($s) =>
                $s->vendorListing
                && $s->vendorListing->productVariant
                && $s->vendorListing->productVariant->product
                && $s->quantity_remaining > 0
            )
            ->map(fn ($s) => $this->listingQuery->toCardShape(
                $s->vendorListing,
                $s->vendorListing->productVariant->product,
                $country,
            ))
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $variantIds
     * @return array<string, array{min_price:int,max_price:int,seller_count:int}>
     */
    private function minPricesByVariant(array $variantIds, Country $country): array
    {
        if (empty($variantIds)) {
            return [];
        }

        return VendorListing::query()
            ->whereIn('product_variant_id', $variantIds)
            ->where('country_id', $country->id)
            ->where('status', VendorListingStatus::Active->value)
            ->whereNull('deleted_at')
            ->selectRaw('product_variant_id, MIN(price) as min_price, MAX(price) as max_price, COUNT(*) as seller_count')
            ->groupBy('product_variant_id')
            ->get()
            ->keyBy('product_variant_id')
            ->map(fn($row) => [
                'min_price' => (int) $row->min_price,
                'max_price' => (int) $row->max_price,
                'seller_count' => (int) $row->seller_count,
            ])
            ->all();
    }

    // ─── 5. flash_sale ──────────────────────────────────────────────────────

    private function hydrateFlashSale(PageBlock $block, Country $country): ?array
    {
        $cfg = $block->config ?? [];

        if (empty($cfg['flash_sale_id'])) {
            // No specific flash sale configured — auto-resolve the current live sale
            // for this country (same fallback logic used in HomeService).
            $flashSale = FlashSale::where('country_id', $block->country_override ?? $country->id)
                ->where('status', 'live')
                ->where('sale_starts_at', '<=', now())
                ->where('sale_ends_at', '>', now())
                ->orderBy('sale_ends_at')
                ->first();

            if (!$flashSale) {
                return null;
            }
        } else {
            $flashSale = FlashSale::find($cfg['flash_sale_id']);

            if (!$flashSale) {
                return null;
            }

            $now = now();
            if (!($flashSale->sale_starts_at <= $now && $now <= $flashSale->sale_ends_at)) {
                return null;
            }
        }

        $maxItems = (int) ($cfg['max_items_shown'] ?? 10);

        // NOTE: flash_sale_submissions has no `position` column in the actual
        // schema (spec assumed one) — ordering falls back to created_at.
        $submissions = FlashSaleSubmission::where('flash_sale_id', $flashSale->id)
            ->where('status', 'approved')
            ->with(['vendorListing.productVariant.product.images'])
            ->orderBy('created_at')
            ->limit($maxItems)
            ->get();

        $items = $submissions
            ->filter(fn($s) => $s->vendorListing)
            ->map(function (FlashSaleSubmission $s) use ($flashSale) {
                // quantity_remaining is read directly off the model — it's a MySQL
                // GENERATED VIRTUAL column (max_quantity_total - quantity_sold);
                // never recompute it in PHP.
                $remaining = (int) $s->quantity_remaining;

                return (new FlashSaleItemResource($s))->toArray(request()) + [
                    'seconds_left' => max(0, now()->diffInSeconds($flashSale->sale_ends_at, false)),
                    'is_sold_out' => $remaining <= 0,
                ];
            })
            ->values()
            ->all();

        return [
            'flash_sale' => [
                'name' => Bilingual::pair($flashSale, 'name'),
                'ends_at' => $flashSale->sale_ends_at->toIso8601String(),
                'seconds_left' => max(0, now()->diffInSeconds($flashSale->sale_ends_at, false)),
            ],
            'show_countdown' => (bool) ($cfg['show_countdown'] ?? true),
            'show_stock_bar' => (bool) ($cfg['show_stock_bar'] ?? true),
            'background_color' => $cfg['background_color'] ?? null,
            'badge_label' => $cfg['badge_label'] ?? null,
            'items' => $items,
        ];
    }

    // ─── 6. deal_of_day ─────────────────────────────────────────────────────

    private function hydrateDealOfDay(PageBlock $block, Country $country): ?array
    {
        $cfg = $block->config ?? [];

        // Resolve the listing — admin listing takes priority over vendor listing
        if (!empty($cfg['admin_listing_id'])) {
            $listing = \App\Models\AdminListing::with('productVariant.product.images')
                ->where('id', $cfg['admin_listing_id'])
                ->where('status', 'active')
                ->first();
        } elseif (!empty($cfg['vendor_listing_id'])) {
            $listing = VendorListing::with('productVariant.product.images')
                ->find($cfg['vendor_listing_id']);

            if ($listing && $listing->status !== VendorListingStatus::Active) {
                $listing = null;
            }
        } else {
            return null;
        }

        if (!$listing) {
            return null;
        }

        $endsAt = isset($cfg['ends_at']) ? \Illuminate\Support\Carbon::parse($cfg['ends_at']) : null;
        if ($endsAt === null || $endsAt->lt(now())) {
            return null;
        }

        $product = $listing->productVariant?->product;
        if (!$product) {
            return null;
        }

        $image = $product->images->firstWhere('is_primary', true) ?? $product->images->first();
        $comparePrice = (int) ($listing->compare_at_price ?? 0);
        $discountPct = $comparePrice > 0
            ? round((($comparePrice - $listing->price) / $comparePrice) * 100, 2)
            : null;

        return [
            'title' => $cfg['title'] ?? null,
            'product' => [
                'name' => Bilingual::pair($product, 'name'),
                'image' => $image?->url,
                'price' => (int) $listing->price,
                'compare_at_price' => $listing->compare_at_price !== null ? (int) $listing->compare_at_price : null,
                'discount_pct' => $discountPct,
                'slug' => $product->slug,
            ],
            'seconds_remaining' => max(0, now()->diffInSeconds($endsAt, false)),
        ];
    }

    // ─── 7. ad_images_2col / ad_images_4col ────────────────────────────────

    private function hydrateAdImages(PageBlock $block, int $limit): array
    {
        $cfg = $block->config ?? [];

        $items = AdImageItem::where('page_block_id', $block->id)
            ->where('is_active', true)
            ->orderBy('position')
            ->limit($limit)
            ->get();

        return [
            'title' => Bilingual::pairFromKeys($cfg, 'title_ar', 'title_en'),
            'aspect_ratio' => $cfg['aspect_ratio'] ?? null,
            'items' => $items->map(fn($i) => [
                'image_url' => $i->file_url,
                'title' => Bilingual::pair($i, 'title'),
                'link_url' => $i->link_url,
                'link_open_new_tab' => (bool) $i->link_open_new_tab,
                'alt_text' => Bilingual::pair($i, 'alt_text'),
                'show_title_overlay' => (bool) $i->show_title_overlay,
                'is_paid' => (bool) $i->is_paid,
            ])->all(),
        ];
    }

    // ─── 8. full_banner ─────────────────────────────────────────────────────

    private function hydrateFullBanner(PageBlock $block): ?array
    {
        $cfg = $block->config ?? [];

        if (empty($cfg['banner_id'])) {
            return null;
        }

        $banner = Banner::with('files')->find($cfg['banner_id']);

        if (!$banner) {
            return null;
        }

        $desktopImage = $banner->files->firstWhere('file_type', 'banner_desktop');
        $mobileImage = $banner->files->firstWhere('file_type', 'banner_mobile');

        if (!$desktopImage) {
            return null;
        }

        return [
            'image_url' => $desktopImage->full_path,
            'mobile_image_url' => $mobileImage?->full_path,
            'link_url' => $banner->cta_url,
            'link_type' => $banner->link_type?->value,
            'link_reference_id' => $banner->link_reference_id,
            'alt_text' => Bilingual::pair($banner, 'title'),
            'aspect_ratio' => $cfg['aspect_ratio'] ?? null,
            'mobile_aspect_ratio' => $cfg['mobile_aspect_ratio'] ?? null,
        ];
    }

    // ─── 10. category_pills ─────────────────────────────────────────────────

    private function hydrateCategoryPills(PageBlock $block, Country $country): array
    {
        $cfg = $block->config ?? [];
        $maxItems = (int) ($cfg['max_items'] ?? 10);
        $showProductCount = (bool) ($cfg['show_product_count'] ?? false);

        $manual = PageBlockCategory::where('page_block_id', $block->id)
            ->orderBy('position')
            ->with('category')
            ->get();

        if ($manual->isNotEmpty()) {
            $categories = $manual->pluck('category')->filter()->values();
        } else {
            $categories = Category::whereNull('parent_id')
                ->where('is_active', true)
                ->where('is_visible', true)
                ->orderBy('sort_order')
                ->limit($maxItems)
                ->get();
        }

        return [
            'title' => Bilingual::pairFromKeys($cfg, 'title_ar', 'title_en'),
            'items' => $categories->map(function ($c) use ($showProductCount) {
                return [
                    'id' => $c->id,
                    'name' => Bilingual::pair($c, 'name'),
                    'slug' => $c->slug,
                    // categories table has no `icon` column in this schema — always null.
                    'icon' => null,
                    'product_count' => $showProductCount ? $this->cachedCategoryProductCount($c->id) : null,
                ];
            })->all(),
        ];
    }

    private function cachedCategoryProductCount(string $categoryId): int
    {
        return (int) $this->cacheRememberTagged(
            "category_product_count:{$categoryId}",
            600,
            ['categories'],
            fn() => (int) Category::where('id', $categoryId)->value('product_count'),
        );
    }

    // ─── 11. brand_strip ────────────────────────────────────────────────────

    private function hydrateBrandStrip(PageBlock $block): array
    {
        $cfg      = $block->config ?? [];
        $maxItems = (int) ($cfg['max_items'] ?? 10);

        $blockBrands = \App\Models\PageBlockBrand::where('page_block_id', $block->id)
            ->orderBy('position')
            ->limit($maxItems)
            ->with('brand')
            ->get();

        return [
            'title'          => Bilingual::pairFromKeys($cfg, 'title_ar', 'title_en'),
            'show_logo_only' => (bool) ($cfg['show_logo_only'] ?? true),
            'brands'         => $blockBrands
                ->filter(fn ($bb) => $bb->brand !== null && $bb->brand->is_active)
                ->map(fn ($bb) => [
                    'id'       => $bb->brand->id,
                    'name'     => ['ar' => $bb->brand->name_ar, 'en' => $bb->brand->name_en],
                    'slug'     => $bb->brand->slug,
                    'logo_url' => $bb->brand->logo_url,
                    'browse_url' => "/browse/brand/{$bb->brand->id}",
                ])
                ->values()
                ->all(),
        ];
    }

    // ─── 12. search_trends ──────────────────────────────────────────────────

    private function hydrateSearchTrends(PageBlock $block, Country $country): array
    {
        $cfg      = $block->config ?? [];
        $maxTerms = (int) ($cfg['max_terms'] ?? 10);
        $source   = $cfg['source'] ?? 'auto';

        if ($source === 'manual' && !empty($cfg['manual_keywords'])) {
            // Admin-defined keywords — no caching needed, served as-is
            $terms = collect(explode("\n", $cfg['manual_keywords']))
                ->map(fn ($t) => trim($t))
                ->filter(fn ($t) => strlen($t) > 0)
                ->take($maxTerms)
                ->values()
                ->all();
        } else {
            // Auto: top queries from search_logs last 7 days, cached 30 min
            $terms = $this->cacheRememberTagged(
                "search_trends:{$country->id}:{$maxTerms}",
                1800,
                ['search_trends'],
                function () use ($country, $maxTerms) {
                    return DB::table('search_logs')
                        ->where('country_id', $country->id)
                        ->where('created_at', '>=', now()->subDays(7))
                        ->whereNotNull('query_normalized')
                        ->whereRaw('LENGTH(query_normalized) > 2')
                        ->selectRaw('query_normalized, COUNT(*) as cnt')
                        ->groupBy('query_normalized')
                        ->orderByDesc('cnt')
                        ->limit($maxTerms)
                        ->pluck('query_normalized')
                        ->all();
                },
            );
        }

        return [
            'title'                 => Bilingual::pairFromKeys($cfg, 'title_ar', 'title_en'),
            'terms'                 => $terms,
            'show_icons'            => (bool) ($cfg['show_icons'] ?? true),
            'show_category_filter'  => (bool) ($cfg['show_category_filter'] ?? false),
        ];
    }

    // ─── 13. text_block ─────────────────────────────────────────────────────

    private function hydrateTextBlock(PageBlock $block): array
    {
        $cfg = $block->config ?? [];

        return [
            'content_html' => [
                'ar' => $this->sanitizeHtml($cfg['content_html_ar'] ?? null),
                'en' => $this->sanitizeHtml($cfg['content_html_en'] ?? null),
            ],
            'text_align' => $cfg['text_align'] ?? 'left',
            'max_width' => $cfg['max_width'] ?? null,
        ];
    }

    /**
     * No HTML purifier package (e.g. mews/purifier) is installed in this project
     * (composer.json has no `purif`/`sanitiz` requirement). As a stopgap, strip
     * everything except a small text-formatting allowlist and drop dangerous
     * attributes (onclick=, javascript: hrefs handled by strip_tags removing all
     * tag attributes for allowed tags too — content is admin-authored but must
     * never execute in a customer's browser).
     * TODO: replace with mews/purifier (or ezyang/htmlpurifier) once installed —
     * strip_tags does not neutralize javascript: URLs inside attributes it keeps.
     */
    private function sanitizeHtml(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $allowed = '<p><br><b><strong><i><em><u><ul><ol><li><span><a><h1><h2><h3><h4>';
        $clean = strip_tags($html, $allowed);

        // Defense-in-depth: drop any remaining event-handler / javascript: attempts
        // that could survive inside the allowed tags above.
        $clean = preg_replace('/on\w+\s*=\s*("[^"]*"|\'[^\']*\')/i', '', $clean);
        $clean = preg_replace('/javascript\s*:/i', '', $clean);

        return $clean;
    }

    // ─── 14. divider ────────────────────────────────────────────────────────

    private function hydrateDivider(PageBlock $block): array
    {
        $cfg = $block->config ?? [];

        return [
            'style' => $cfg['style'] ?? 'solid',
            'color' => $cfg['color'] ?? null,
            'margin_top' => $cfg['margin_top'] ?? 0,
            'margin_bottom' => $cfg['margin_bottom'] ?? 0,
        ];
    }

    // ─── 15. newsletter_signup ──────────────────────────────────────────────

    private function hydrateNewsletterSignup(PageBlock $block): array
    {
        $cfg = $block->config ?? [];

        return [
            'title' => Bilingual::pairFromKeys($cfg, 'title_ar', 'title_en'),
            'subtitle' => Bilingual::pairFromKeys($cfg, 'subtitle_ar', 'subtitle_en'),
            'subscribe_url' => '/api/customer/v1/{country}/newsletter/subscribe',
            'placeholder_email' => ['ar' => 'بريدك الإلكتروني', 'en' => 'Your email address'],
            'button_label' => ['ar' => 'اشترك الآن', 'en' => 'Subscribe Now'],
        ];
    }

    // ─── 16. mega_deals ─────────────────────────────────────────────────────

    private function hydrateMegaDeals(PageBlock $block, Country $country): array
    {
        $cfg  = $block->config ?? [];
        $endsAt = isset($cfg['ends_at']) ? \Carbon\Carbon::parse($cfg['ends_at']) : null;
        $tabs = $cfg['tabs'] ?? [];

        $resolvedTabs = collect($tabs)->map(function ($tab) use ($country) {
            if (empty($tab['category_id'])) return null;

            $categoryIds = [];
            $rootCat = \App\Models\Category::find($tab['category_id']);
            if ($rootCat) {
                $categoryIds = app(\App\Services\Customer\CategoryService::class)
                    ->getDescendantIds($rootCat);
            }

            $maxProducts = (int) ($tab['max_products'] ?? 4);

            $listings = $this->getBuyBoxProducts($country, $categoryIds, $maxProducts);

            return [
                'label'       => ['ar' => $tab['label_ar'] ?? null, 'en' => $tab['label_en'] ?? null],
                'category_id' => $tab['category_id'],
                'browse_url'  => "/browse/product/{$tab['category_id']}",
                'products'    => $listings,
            ];
        })->filter()->values()->all();

        return [
            'title'          => Bilingual::pairFromKeys($cfg, 'title_ar', 'title_en'),
            'show_countdown' => (bool) ($cfg['show_countdown'] ?? true),
            'seconds_remaining' => $endsAt ? max(0, now()->diffInSeconds($endsAt, false)) : null,
            'ends_at'        => $endsAt?->toIso8601String(),
            'columns'        => (int) ($cfg['columns'] ?? 2),
            'show_view_all'  => (bool) ($cfg['show_view_all'] ?? true),
            'tabs'           => $resolvedTabs,
        ];
    }

    // ─── 17. promo_tiles ────────────────────────────────────────────────────

    private function hydratePromoTiles(PageBlock $block): array
    {
        $cfg   = $block->config ?? [];
        $tiles = collect($cfg['tiles'] ?? [])->map(fn ($tile) => [
            'label'          => ['ar' => $tile['label_ar'] ?? null,      'en' => $tile['label_en'] ?? null],
            'badge'          => ['ar' => $tile['badge_label_ar'] ?? null, 'en' => $tile['badge_label_en'] ?? null],
            'image_url'      => $tile['image_url'] ?? null,
            'link_url'       => $tile['link_url'] ?? null,
            'is_paid'        => (bool) ($tile['is_paid'] ?? false),
        ])->all();

        $gridCols = max(1, min(8, (int) ($cfg['grid_cols'] ?? $cfg['columns'] ?? 2)));
        $gridRows = max(1, min(8, (int) ($cfg['grid_rows'] ?? 2)));

        return [
            'title'     => Bilingual::pairFromKeys($cfg, 'title_ar', 'title_en'),
            'grid_cols' => $gridCols,
            'grid_rows' => $gridRows,
            'tiles'     => $tiles,
        ];
    }

    // ─── 18. image_slider ───────────────────────────────────────────────────

    private function hydrateImageSlider(PageBlock $block): array
    {
        $cfg = $block->config ?? [];

        $items = AdImageItem::where('page_block_id', $block->id)
            ->where('is_active', true)
            ->orderBy('position')
            ->get()
            ->map(fn ($img) => [
                'image_url'      => $img->file_url,
                'link_url'       => $img->link_url,
                'link_new_tab'   => (bool) $img->link_open_new_tab,
                'title'          => ['ar' => $img->title_ar,    'en' => $img->title_en],
                'subtitle'       => ['ar' => $img->subtitle_ar, 'en' => $img->subtitle_en],
                'badge'          => ['ar' => $img->badge_label_ar, 'en' => $img->badge_label_en],
                'alt'            => ['ar' => $img->alt_text_ar, 'en' => $img->alt_text_en],
                'is_paid'        => (bool) $img->is_paid,
            ])->all();

        return [
            'title'        => Bilingual::pairFromKeys($cfg, 'title_ar', 'title_en'),
            'columns'      => (int) ($cfg['columns'] ?? 5),
            'rows'         => (int) ($cfg['rows'] ?? 1),
            'scrollable'   => (bool) ($cfg['scrollable'] ?? true),
            'show_label'   => (bool) ($cfg['show_label'] ?? true),
            'show_badge'   => (bool) ($cfg['show_badge'] ?? false),
            'image_shape'  => $cfg['image_shape']  ?? 'rounded',
            'size_preset'  => $cfg['size_preset']  ?? 'medium',
            'aspect_ratio' => $cfg['aspect_ratio'] ?? '',
            'items'        => $items,
            'total_items'  => count($items),
        ];
    }

    // ─── 19. sponsored_grid ─────────────────────────────────────────────────

    private function hydrateSponsoredGrid(PageBlock $block, Country $country): array
    {
        $cfg         = $block->config ?? [];
        $source      = $cfg['source'] ?? 'sponsored';
        $maxProducts = (int) ($cfg['max_products'] ?? 20);

        if ($source === 'sponsored') {
            $adminListings = \App\Models\AdminListing::where('country_id', $country->id)
                ->where('status', 'active')
                ->where('search_boost', '>', 0)
                ->with(['productVariant.product.images', 'primaryShippingMethod'])
                ->orderByDesc('search_boost')
                ->limit((int) ceil($maxProducts / 2))
                ->get();

            $vendorListings = VendorListing::where('country_id', $country->id)
                ->where('status', VendorListingStatus::Active->value)
                ->whereHas('vendor', fn ($q) => $q->where('global_status', VendorGlobalStatus::Active->value))
                ->where('score', '>', 0)
                ->with(['vendor', 'productVariant.product.images', 'primaryShippingMethod'])
                ->orderByDesc('score')
                ->limit((int) ceil($maxProducts / 2))
                ->get();

            $cards = collect();
            foreach ($adminListings as $al) {
                if ($al->productVariant?->product) {
                    $cards->push($this->listingQuery->toMixedCardShape($al, $al->productVariant->product, $country));
                }
            }
            foreach ($vendorListings as $vl) {
                if ($vl->productVariant?->product) {
                    $cards->push($this->listingQuery->toMixedCardShape($vl, $vl->productVariant->product, $country));
                }
            }
            $products = $cards->shuffle()->take($maxProducts)->values()->all();
        } else {
            $products = $this->productRowQueried($source, $cfg, $country, $maxProducts);
        }

        return [
            'title'                => Bilingual::pairFromKeys($cfg, 'title_ar', 'title_en'),
            'columns'              => (int) ($cfg['columns'] ?? 5),
            'show_sponsored_badge' => (bool) ($cfg['show_sponsored_badge'] ?? true),
            'products'             => $products,
        ];
    }

    // ─── 20. app_download_banner ────────────────────────────────────────────

    private function hydrateAppDownloadBanner(PageBlock $block): array
    {
        $cfg = $block->config ?? [];

        return [
            'title'                => Bilingual::pairFromKeys($cfg, 'title_ar', 'title_en'),
            'subtitle'             => Bilingual::pairFromKeys($cfg, 'subtitle_ar', 'subtitle_en'),
            'background_color'     => $cfg['background_color']     ?? '#FEE200',
            'background_image_url' => $cfg['background_image_url'] ?? null,
            'app_store_url'        => $cfg['app_store_url']        ?? null,
            'play_store_url'       => $cfg['play_store_url']       ?? null,
            'app_store_badge_url'  => $cfg['app_store_badge_url']  ?? null,
            'play_store_badge_url' => $cfg['play_store_badge_url'] ?? null,
            'phone_mockup_url'     => $cfg['phone_mockup_url']     ?? null,
        ];
    }

    /**
     * Helper: get buy-box product cards for a set of category IDs.
     */
    private function getBuyBoxProducts(Country $country, array $categoryIds, int $limit): array
    {
        $products = \App\Models\Product::whereIn('category_id', $categoryIds)
            ->where('status', 'active')
            ->whereHas('productCountrySettings', fn ($q) =>
                $q->where('country_id', $country->id)->where('is_available', true)
            )
            ->with(['variants', 'images'])
            ->orderByDesc('rating_avg')
            ->limit($limit)
            ->get();

        $buyBox = $this->listingQuery->getBuyBoxForProducts($products, $country);

        return $products
            ->map(fn ($p) => [$p, $buyBox[$p->id] ?? null])
            ->filter(fn ($pair) => $pair[1] !== null)
            ->map(fn ($pair) => $this->listingQuery->toMixedCardShape($pair[1], $pair[0], $country))
            ->values()
            ->all();
    }

    // ─── Cache helper ───────────────────────────────────────────────────────

    /**
     * Cache::tags() is not supported by the 'database' cache driver configured
     * in this project (CACHE_STORE=database) — only by taggable stores like
     * redis/memcached. Fall back to a plain (untagged) remember() when tags
     * aren't supported so this still works today; tags will kick in for free
     * once the cache store is switched to redis.
     */
    private function cacheRememberTagged(string $key, int $ttl, array $tags, \Closure $callback): mixed
    {
        $store = Cache::getStore();

        if ($store instanceof \Illuminate\Cache\TaggableStore) {
            return Cache::tags($tags)->remember($key, $ttl, $callback);
        }

        return Cache::remember($key, $ttl, $callback);
    }
}
