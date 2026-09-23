<?php

namespace App\Services\Shared;

use App\Models\AdImageItem;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Country;
use App\Models\FlashSale;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\PageSection;
use App\Models\Product;
use App\Models\ProductCountrySetting;
use App\Services\Ads\PaidAdInjector;
use App\Services\Customer\ListingQueryService;
use App\Services\Customer\UnifiedListingQueryService;
use App\Support\Bilingual;
use App\Support\SafeCache;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PageBuilderService
{
    private const BUY_BOX_ORDER = [
        'express_fbn' => 0,
        'merchant_fbp' => 1,
        'marketplace' => 2,
    ];

    /**
     * Pass-1 bulk-loaded maps, populated once per buildSkeleton() call and
     * consumed by hydrateBlock() below instead of querying per block.
     */
    private Collection $bannersById;
    private Collection $brandStripRowsByBlock;
    private Collection $adminListingsByVariant;
    private Collection $vendorListingsByVariant;

    public function __construct(
        private readonly ListingQueryService $listingQuery,
        private readonly UnifiedListingQueryService $unifiedQuery,
        private readonly PaidAdInjector $adInjector,
    ) {
    }

    /**
     * Resolve the active published page for the given type + reference,
     * filtered by device_target, audience, A/B variant, and country_override.
     * Returns null if no matching published page exists — callers degrade gracefully.
     */
    public function resolve(
        Country $country,
        string $pageType,
        ?string $referenceId,
        string $deviceTarget = 'all',
        string $audience = 'guest',
    ): ?array {
        $page = Page::where('country_id', $country->id)
            ->where('page_type', $pageType)
            ->when(
                $referenceId !== null,
                fn($q) => $q->where('reference_id', $referenceId),
                fn($q) => $q->whereNull('reference_id'),
            )
            ->where('status', 'published')
            ->where(fn($q) => $q->whereNull('publish_at')->orWhere('publish_at', '<=', now()))
            ->where(fn($q) => $q->whereNull('unpublish_at')->orWhere('unpublish_at', '>', now()))
            ->orderByDesc('is_default')
            ->orderByDesc('published_at')
            ->first();

        if (!$page) {
            return null;
        }

        $sessionId = request()?->header('X-Session-Id') ?? request()?->cookie('session_id');

        // enhancement.md P-20 task 3: cache the whole rendered skeleton (every
        // block EXCEPT personalised/dynamic fragments — ads and wishlist state
        // are injected below, after the cache read/write) keyed by
        // (page_id, version, country, device_target, audience). The page's
        // `version` column is bumped on every publish, so a publish
        // invalidates this key for free; PageCacheService::bustPage()/
        // bustBlock() additionally forget the current-version keys so a
        // draft-only block edit (which does not change `version`) also busts
        // it, per task 3's explicit invalidation requirement.
        $cacheTtl = $this->skeletonCacheTtl($page);
        $cacheKey = self::skeletonCacheKey($page, $country->id, $deviceTarget, $audience);

        $skeleton = SafeCache::tags(["page:{$page->id}"])->remember(
            $cacheKey,
            $cacheTtl,
            fn () => $this->buildSkeleton($page, $country, $deviceTarget, $audience),
        );

        return $this->injectDynamicFragments($skeleton, $country, $sessionId);
    }

    /**
     * (page_id, version, country, device_target, audience) -> cache key.
     * Locale isn't part of the key: every bilingual field is returned as an
     * {ar, en} pair already, so the payload is identical across locales.
     */
    public static function skeletonCacheKey(Page $page, string $countryId, string $deviceTarget, string $audience): string
    {
        return "home_render:{$page->id}:{$page->version}:{$countryId}:{$deviceTarget}:{$audience}";
    }

    /**
     * Respect page_blocks.cache_ttl_seconds: use the shortest TTL configured
     * on any of the page's blocks, so a block explicitly configured for
     * near-real-time data (e.g. a flash sale counting down) doesn't get held
     * stale by a longer page-level default. Falls back to 120s, and product
     * blocks are effectively capped at 60s per the doc's guidance.
     */
    private function skeletonCacheTtl(Page $page): int
    {
        $minTtl = $page->blocks()
            ->where('is_visible', true)
            ->whereNotNull('cache_ttl_seconds')
            ->min('cache_ttl_seconds');

        if ($minTtl !== null) {
            return max(1, (int) $minTtl);
        }

        $hasProductBlock = $page->blocks()
            ->where('is_visible', true)
            ->whereIn('block_type', ['product_row', 'flash_sale', 'deal_of_day', 'mega_deals', 'sponsored_grid'])
            ->exists();

        return $hasProductBlock ? 60 : 120;
    }

    /**
     * Builds every section/block EXCEPT ad injection — the part that is safe
     * to cache and share across every visitor with the same
     * (country, device_target, audience).
     */
    private function buildSkeleton(Page $page, Country $country, string $deviceTarget, string $audience): array
    {
        $now = now();

        $blocks = $page->blocks()
            ->where('is_visible', true)
            ->where(fn($q) => $q->whereNull('visible_from')->orWhere('visible_from', '<=', $now))
            ->where(fn($q) => $q->whereNull('visible_until')->orWhere('visible_until', '>', $now))
            ->whereIn('device_target', ['all', $deviceTarget])
            ->whereIn('audience', ['all', $audience])
            ->where(fn($q) => $q->whereNull('country_override')->orWhere('country_override', $country->id))
            ->orderBy('position')
            ->with([
                'slides' => fn($q) => $q->where('is_active', true)
                    ->where(fn($q2) => $q2->whereNull('visible_from')->orWhere('visible_from', '<=', $now))
                    ->where(fn($q2) => $q2->whereNull('visible_until')->orWhere('visible_until', '>', $now))
                    ->orderBy('position'),
                'adImageItems' => fn($q) => $q->where('is_active', true)->orderBy('position'),
                'blockProducts' => fn($q) => $q->orderBy('position'),
                'blockProducts.productVariant.product.images',
                'blockProducts.productVariant.product.category',
                'blockProducts.productVariant.product.brand',
                'blockSellers' => fn($q) => $q->orderBy('position'),
                'blockSellers.seller',
                'blockCategories' => fn($q) => $q->orderBy('position'),
                'blockCategories.category',
                // brand_strip brands are bulk-loaded once in buildSkeleton()
                // via $this->brandStripRowsByBlock — no 'blockBrands' eager
                // load needed here.
            ])
            ->get();

        $sections = PageSection::where('page_id', $page->id)
            ->where('is_visible', true)
            ->orderBy('position')
            ->get();

        // ── Pass 1: collect every ID needed by ALL visible blocks up front ──
        // (enhancement.md P-20 task 1) so pass 2 below can bulk-load each
        // type ONCE instead of once per block.
        $bannerIds = $blocks
            ->where('block_type', 'full_banner')
            ->map(fn (PageBlock $b) => $b->config['banner_id'] ?? null)
            ->filter()
            ->unique()
            ->values();

        $this->bannersById = $bannerIds->isEmpty()
            ? collect()
            : Banner::with('files')->whereIn('id', $bannerIds)->get()->keyBy('id');

        $brandStripBlockIds = $blocks->where('block_type', 'brand_strip')->pluck('id');

        $this->brandStripRowsByBlock = $brandStripBlockIds->isEmpty()
            ? collect()
            : \App\Models\PageBlockBrand::whereIn('page_block_id', $brandStripBlockIds)
                ->orderBy('position')
                ->with('brand')
                ->get()
                ->groupBy('page_block_id');

        // Manual product_row blocks resolve each product's buy-box — batch
        // that across EVERY block's blockProducts pivot rows in one pair of
        // whereIn() queries instead of ListingQueryService::getForVariant()
        // per product (was 1-2 queries per product, per block).
        $manualVariantIds = $blocks
            ->flatMap(fn (PageBlock $b) => $b->blockProducts->pluck('product_variant_id'))
            ->filter()
            ->unique()
            ->values();

        if ($manualVariantIds->isEmpty()) {
            $this->adminListingsByVariant = collect();
            $this->vendorListingsByVariant = collect();
        } else {
            // Pre-warm ListingImageResolver (P-17) so buildImagesSlider() calls
            // inside toCardShape() below don't re-query per product.
            app(\App\Services\Media\ListingImageResolver::class)->forVariants($manualVariantIds->all());

            $this->adminListingsByVariant = \App\Models\AdminListing::query()
                ->whereIn('product_variant_id', $manualVariantIds)
                ->where('country_id', $country->id)
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->with([
                    'primaryShippingMethod:id,name,badge_label_en,badge_label_ar,badge_color_hex,badge_text_color_hex,badge_icon_color_hex,badge_image_path,min_delivery_days,max_delivery_days,is_express_type,badge_show_delivery_time,badge_delivery_text_en,badge_delivery_text_ar,badge_icon',
                    'productVariant:id,sku',
                ])
                ->orderBy('price')
                ->get()
                ->map(function ($al) {
                    $al->setAttribute('listing_type', 'admin');
                    $al->setAttribute('vendor', null);
                    return $al;
                })
                ->groupBy('product_variant_id');

            $this->vendorListingsByVariant = \App\Models\VendorListing::query()
                ->whereIn('product_variant_id', $manualVariantIds)
                ->where('country_id', $country->id)
                ->where('status', \App\Enums\VendorListingStatus::Active->value)
                ->whereHas('vendor', fn ($q) => $q->where('global_status', \App\Enums\VendorGlobalStatus::Active->value))
                ->with([
                    'vendor:id,store_name,store_rating_avg,store_rating_count',
                    'primaryShippingMethod:id,name,badge_label_en,badge_label_ar,badge_color_hex,badge_text_color_hex,badge_icon_color_hex,badge_image_path,min_delivery_days,max_delivery_days,is_express_type,badge_show_delivery_time,badge_delivery_text_en,badge_delivery_text_ar,badge_icon',
                    'productVariant:id,sku',
                ])
                ->orderByRaw('score IS NULL, score DESC')
                ->orderByRaw('rating_avg IS NULL, rating_avg DESC')
                ->orderByDesc('rating_count')
                ->orderBy('price')
                ->get()
                ->map(function ($vl) {
                    $vl->setAttribute('listing_type', 'vendor');
                    return $vl;
                })
                ->groupBy('product_variant_id');
        }

        $blocksBySection = $blocks->groupBy('section_id');

        $sectionsData = $sections->map(function ($section) use ($blocksBySection, $country) {
            $sectionBlocks = $blocksBySection->get($section->id, collect());

            // For column-layout sections, group blocks by column_index
            if ($section->layout === 'columns') {
                $columns = collect($sectionBlocks)
                    ->groupBy('column_index')
                    ->sortKeys()
                    ->map(fn ($colBlocks) =>
                        $colBlocks->map(fn (PageBlock $b) => $this->hydrateBlock($b, $country))->values()->all()
                    )->values()->all();

                return [
                    'id'                   => $section->id,
                    'name'                 => $section->name,
                    'name_i18n'            => Bilingual::pair($section, 'name'),
                    'position'             => $section->position,
                    'layout'               => 'columns',
                    'columns_config'       => is_string($section->columns_config)
                        ? json_decode($section->columns_config, true)
                        : $section->columns_config,
                    'background_color'     => $section->background_color,
                    'background_image_url' => Bilingual::pair($section, 'background_image_url'),
                    'background_image_type' => $section->background_image_type ?? 'section',
                    'columns'              => $columns,   // array of column arrays
                    'blocks'               => [],         // empty for column-layout sections
                ];
            }

            // Default: stack layout
            return [
                'id'               => $section->id,
                'name'             => $section->name,
                'name_i18n'        => Bilingual::pair($section, 'name'),
                'position'         => $section->position,
                'layout'           => 'stack',
                'columns_config'   => is_string($section->columns_config)
                    ? json_decode($section->columns_config, true)
                    : $section->columns_config,
                'background_color'     => $section->background_color,
                'background_image_url' => Bilingual::pair($section, 'background_image_url'),
                'background_image_type' => $section->background_image_type ?? 'section',
                'columns'          => [],
                'blocks'           => $sectionBlocks
                    ->map(fn (PageBlock $b) => $this->hydrateBlock($b, $country))
                    ->values()
                    ->all(),
            ];
        })->values()->all();

        return [
            'page_id' => $page->id,
            'page_type' => $page->page_type,
            'version' => $page->version,
            'seo' => [
                'title' => $page->seo_title,
                'description' => $page->seo_description,
                'og_image_url' => $page->og_image_url,
            ],
            'sections' => $sectionsData,
            'blocks' => $blocks
                ->filter(fn (PageBlock $b) => is_null($b->section_id))
                ->map(fn (PageBlock $b) => $this->hydrateBlock($b, $country))
                ->values()
                ->all(),
            'has_sections'      => count($sectionsData) > 0,
            'total_block_count' => $blocks->count(),
        ];
    }

    /**
     * Walk the (possibly cached) skeleton and inject personalised/dynamic
     * fragments — sponsored ads (PaidAdInjector) and, in the future, wishlist
     * state — AFTER the cached payload is read. This is what keeps
     * per-visitor state out of the shared cache entry (task 3).
     */
    private function injectDynamicFragments(array $skeleton, Country $country, ?string $sessionId): array
    {
        $inject = fn (array $block) => $this->adInjector->injectFlat($block, $country->id, $sessionId);

        foreach ($skeleton['sections'] as &$section) {
            if (!empty($section['columns'])) {
                foreach ($section['columns'] as &$column) {
                    $column = array_values(array_map($inject, $column));
                }
            }
            if (!empty($section['blocks'])) {
                $section['blocks'] = array_values(array_map($inject, $section['blocks']));
            }
        }
        unset($section);

        $skeleton['blocks'] = array_values(array_map($inject, $skeleton['blocks']));

        return $skeleton;
    }

    /**
     * Attach whichever block-specific relations have data (slides, ad images,
     * products, sellers, categories) alongside the base block fields.
     */
    private function hydrateBlock(PageBlock $b, Country $country): array
    {
        $data = [
            'id'               => $b->id,
            'block_type'       => $b->block_type,
            'position'         => $b->position,
            'device_target'    => $b->device_target,
            'background_color' => ($b->config['background_color'] ?? null),
            'config'           => $b->config,
        ];

        if ($b->block_type === 'full_banner' && !empty($b->config['banner_id'])) {
            $banner = $this->bannersById[$b->config['banner_id']] ?? null;

            if ($banner) {
                $desktopImageEn = $banner->files->firstWhere('file_type', 'banner_desktop_en');
                $desktopImageAr = $banner->files->firstWhere('file_type', 'banner_desktop_ar');
                $mobileImageEn  = $banner->files->firstWhere('file_type', 'banner_mobile_en');
                $mobileImageAr  = $banner->files->firstWhere('file_type', 'banner_mobile_ar');

                if ($desktopImageEn || $desktopImageAr) {
                    $data['banner'] = [
                        'image_url'           => [
                            'en' => $desktopImageEn?->full_path,
                            'ar' => $desktopImageAr?->full_path ?? $desktopImageEn?->full_path,
                        ],
                        'mobile_image_url'    => [
                            'en' => $mobileImageEn?->full_path,
                            'ar' => $mobileImageAr?->full_path ?? $mobileImageEn?->full_path,
                        ],
                        'link_url'            => $banner->cta_url,
                        'link_type'           => $banner->link_type?->value,
                        'link_reference_id'   => $banner->link_reference_id,
                        'alt_text'            => [
                            'ar' => $banner->title_ar,
                            'en' => $banner->title_en,
                        ],
                        'aspect_ratio'        => $b->config['aspect_ratio'] ?? '4:1',
                        'mobile_aspect_ratio' => $b->config['mobile_aspect_ratio'] ?? '2:1',
                    ];
                }
            }
        }

        if ($b->block_type === 'search_trends') {
            $cfg      = $b->config ?? [];
            $maxTerms = (int) ($cfg['max_terms'] ?? 10);
            $source   = $cfg['source'] ?? 'auto';

            if ($source === 'manual' && !empty($cfg['manual_keywords'])) {
                $terms = collect(explode("\n", $cfg['manual_keywords']))
                    ->map(fn ($t) => trim($t))
                    ->filter(fn ($t) => strlen($t) > 0)
                    ->take($maxTerms)
                    ->values()
                    ->all();
            } else {
                $terms = \Illuminate\Support\Facades\DB::table('search_logs')
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
            }

            $data['terms'] = $terms;
            $data['title'] = [
                'ar' => $cfg['title_ar'] ?? null,
                'en' => $cfg['title_en'] ?? null,
            ];
            $data['show_icons']           = (bool) ($cfg['show_icons'] ?? true);
            $data['show_category_filter'] = (bool) ($cfg['show_category_filter'] ?? false);
        }

        if ($b->slides->isNotEmpty()) {
            $data['slides'] = $b->slides->map(fn($s) => [
                'id' => $s->id,
                'position' => $s->position,
                'desktop_url' => Bilingual::pair($s, 'desktop_url'),
                'mobile_url' => Bilingual::pair($s, 'mobile_url'),
                'title' => Bilingual::pair($s, 'title'),
                'subtitle' => Bilingual::pair($s, 'subtitle'),
                'cta_label' => Bilingual::pair($s, 'cta_label'),
                'cta_url' => $s->cta_url,
                'cta_open_new_tab' => $s->cta_open_new_tab,
                'text_color' => $s->text_color,
                'text_position' => $s->text_position,
                'overlay_opacity' => (float) $s->overlay_opacity,
                'link_type' => $s->link_type,
                'link_reference_id' => $s->link_reference_id,
                'is_paid' => (bool) $s->is_paid,
            ])->values()->all();
        }

        if ($b->adImageItems->isNotEmpty()) {
            $data['items'] = $b->adImageItems->map(fn($i) => [
                'id' => $i->id,
                'position' => $i->position,
                'url' => Bilingual::pair($i, 'file_url'),
                'title' => Bilingual::pair($i, 'title'),
                'link_url' => $i->link_url,
                'link_open_new_tab' => $i->link_open_new_tab,
                'alt_text' => Bilingual::pair($i, 'alt_text'),
                'show_title_overlay' => $i->show_title_overlay,
                'aspect_ratio' => $i->aspect_ratio,
                'is_paid' => (bool) $i->is_paid,
            ])->values()->all();
        }

        if ($b->blockProducts->isNotEmpty()) {
            $primeTuples = [];
            foreach ($b->blockProducts as $bp) {
                $l = $this->adminListingsByVariant[$bp->productVariant?->id][0] ?? $this->vendorListingsByVariant[$bp->productVariant?->id][0] ?? null;
                if ($l) {
                    $primeTuples[] = [\App\Services\Customer\PromoBadgeResolver::typeOf($l), $l->id, $bp->productVariant->product_id];
                }
            }
            \App\Services\Customer\PromoBadgeResolver::instance()->prime($primeTuples);
            $data['products'] = $b->blockProducts
                ->filter(fn ($bp) => $bp->productVariant?->product !== null)
                ->map(function ($bp) use ($country) {
                    $variantId = $bp->productVariant->id;
                    $product   = $bp->productVariant->product;

                    // Admin listing wins over vendor listing — resolved from the
                    // pass-1 bulk-loaded maps built once per buildSkeleton() call
                    // (was ListingQueryService::getForVariant() per product).
                    $listing = $this->adminListingsByVariant[$variantId][0]
                        ?? $this->vendorListingsByVariant[$variantId][0]
                        ?? null;

                    if (! $listing) {
                        return null; // No active listing in this country — skip
                    }

                    return $this->listingQuery->toMixedCardShape($listing, $product, $country);
                })
                ->filter()  // remove null (no listing) entries
                ->values()
                ->all();
        }

        if ($b->blockSellers->isNotEmpty()) {
            $data['sellers'] = $b->blockSellers
                ->filter(fn($bs) => $bs->seller !== null)
                ->map(fn($bs) => [
                    'id' => $bs->seller->id,
                    'store_name' => $bs->seller->store_name,
                    'store_slug' => $bs->seller->store_slug,
                    'avatar' => $bs->seller->avatar,
                    'rating_avg' => (float) $bs->seller->store_rating_avg,
                    'rating_count' => (int) $bs->seller->store_rating_count,
                ])
                ->values()
                ->all();
        }

        if ($b->blockCategories->isNotEmpty()) {
            $data['categories'] = $b->blockCategories
                ->filter(fn($bc) => $bc->category !== null)
                ->map(fn($bc) => [
                    'id' => $bc->category->id,
                    'name' => Bilingual::pair($bc->category, 'name'),
                    'slug' => $bc->category->slug,
                ])
                ->values()
                ->all();
        }

        if ($b->block_type === 'brand_strip') {
            $maxItems = (int) ($b->config['max_items'] ?? 10);
            $brands   = ($this->brandStripRowsByBlock[$b->id] ?? collect())->take($maxItems);

            $data['brands'] = $brands
                ->filter(fn ($bb) => $bb->brand?->is_active)
                ->map(fn ($bb) => [
                    'id'         => $bb->brand->id,
                    'name'       => ['ar' => $bb->brand->name_ar, 'en' => $bb->brand->name_en],
                    'slug'       => $bb->brand->slug,
                    'logo_url'   => $bb->brand->logo_url,
                    'browse_url' => "/browse/brand/{$bb->brand->id}",
                ])
                ->values()
                ->all();
        }

        // ── newsletter_signup ─────────────────────────────────────────────────────
        if ($b->block_type === 'newsletter_signup') {
            $cfg = $b->config ?? [];
            $data['title']              = ['ar' => $cfg['title_ar'] ?? null, 'en' => $cfg['title_en'] ?? null];
            $data['subtitle']           = ['ar' => $cfg['subtitle_ar'] ?? null, 'en' => $cfg['subtitle_en'] ?? null];
            $data['subscribe_url']      = '/api/customer/v1/{country}/newsletter/subscribe';
            $data['placeholder_email']  = ['ar' => 'بريدك الإلكتروني', 'en' => 'Your email address'];
            $data['button_label']       = ['ar' => 'اشترك الآن', 'en' => 'Subscribe Now'];
        }

        if ($b->block_type === 'mega_deals') {
            $cfg  = $b->config ?? [];

            $productIds = \App\Models\PageBlockProduct::where('page_block_id', $b->id)
                ->with('productVariant:id,product_id')
                ->orderBy('position')
                ->get()
                ->pluck('productVariant.product_id')
                ->filter()
                ->unique()
                ->values();

            $products = [];
            if ($productIds->isNotEmpty()) {
                $productModels = Product::query()
                    ->whereIn('id', $productIds)
                    ->where('status', 'active')
                    ->whereNotIn('id', ProductCountrySetting::where('country_id', $country->id)
                        ->where('is_available', false)
                        ->pluck('product_id'))
                    ->with(['variants', 'images'])
                    ->get()
                    ->sortBy(fn ($p) => $productIds->search($p->id))
                    ->values();

                $products = $this->productsToCards($productModels, $country);
            }

            $endsAt = isset($cfg['ends_at']) ? \Carbon\Carbon::parse($cfg['ends_at']) : null;
            $data['title']           = ['ar' => $cfg['title_ar'] ?? null, 'en' => $cfg['title_en'] ?? null];
            $data['show_countdown']  = (bool) ($cfg['show_countdown'] ?? true);
            $data['seconds_remaining'] = $endsAt ? max(0, now()->diffInSeconds($endsAt, false)) : null;
            $data['ends_at']         = $endsAt?->toIso8601String();
            $data['columns']         = (int) ($cfg['columns'] ?? 2);
            $data['show_view_all']   = (bool) ($cfg['show_view_all'] ?? true);
            $data['view_all_url']    = $cfg['view_all_url'] ?? null;
            $data['products']        = $products;
        }

        if ($b->block_type === 'image_slider') {
            $cfg   = $b->config ?? [];
            $items = $b->adImageItems
                ->map(fn ($img) => [
                    'image_url' => Bilingual::pair($img, 'file_url'),
                    'link_url'  => $img->link_url,
                    'title'     => ['ar' => $img->title_ar, 'en' => $img->title_en],
                    'subtitle'  => ['ar' => $img->subtitle_ar, 'en' => $img->subtitle_en],
                    'badge'     => ['ar' => $img->badge_label_ar, 'en' => $img->badge_label_en],
                ])->all();

            $data['title']        = ['ar' => $cfg['title_ar'] ?? null, 'en' => $cfg['title_en'] ?? null];
            $data['columns']      = (int) ($cfg['columns'] ?? 5);
            $data['rows']         = (int) ($cfg['rows'] ?? 1);
            $data['scrollable']   = (bool) ($cfg['scrollable'] ?? true);
            $data['show_label']   = (bool) ($cfg['show_label'] ?? true);
            $data['show_badge']   = (bool) ($cfg['show_badge'] ?? false);
            $data['image_shape']  = $cfg['image_shape']  ?? 'rounded';
            $data['size_preset']  = $cfg['size_preset']  ?? 'medium';
            $data['aspect_ratio'] = $cfg['aspect_ratio'] ?? '';
            $data['items']        = $items;
        }

        if ($b->block_type === 'promo_tiles') {
            $cfg = $b->config ?? [];
            $data['title']   = ['ar' => $cfg['title_ar'] ?? null, 'en' => $cfg['title_en'] ?? null];
            $data['columns'] = (int) ($cfg['grid_cols'] ?? $cfg['columns'] ?? 2);
            $data['rows']    = (int) ($cfg['grid_rows'] ?? 1);
            $data['tiles']   = collect($cfg['tiles'] ?? [])->map(fn ($tile) => [
                'label'        => ['ar' => $tile['label_ar'] ?? null,      'en' => $tile['label_en'] ?? null],
                'badge'        => ['ar' => $tile['badge_label_ar'] ?? null, 'en' => $tile['badge_label_en'] ?? null],
                'image_url'    => [
                    'en' => $tile['image_url_en'] ?? $tile['image_url'] ?? null,
                    'ar' => $tile['image_url_ar'] ?? ($tile['image_url_en'] ?? $tile['image_url'] ?? null),
                ],
                'link_url'     => $tile['link_url'] ?? null,
                'is_paid'      => (bool) ($tile['is_paid'] ?? false),
            ])->all();
        }

        if ($b->block_type === 'ad_images_3col') {
            $cfg = $b->config ?? [];
            $data['title']        = ['ar' => $cfg['title_ar'] ?? null, 'en' => $cfg['title_en'] ?? null];
            $data['columns']      = 3;
            $data['aspect_ratio'] = $cfg['aspect_ratio'] ?? '4:3';
            $data['images']       = $b->adImageItems->take(3)->map(fn ($img) => [
                'image_url' => Bilingual::pair($img, 'file_url'),
                'link_url'  => $img->link_url,
                'title'     => ['ar' => $img->title_ar, 'en' => $img->title_en],
                'subtitle'  => ['ar' => $img->subtitle_ar, 'en' => $img->subtitle_en],
                'is_paid'   => (bool) $img->is_paid,
            ])->all();
        }

        if ($b->block_type === 'sponsored_grid') {
            $cfg      = $b->config ?? [];
            $maxProd  = (int) ($cfg['max_products'] ?? 20);
            $products = $this->resolveDynamicProducts($b, $country) ?? [];
            $data['title']                = ['ar' => $cfg['title_ar'] ?? null, 'en' => $cfg['title_en'] ?? null];
            $data['columns']              = (int) ($cfg['columns'] ?? 5);
            $data['show_sponsored_badge'] = (bool) ($cfg['show_sponsored_badge'] ?? true);
            $data['products']             = array_slice($products, 0, $maxProd);
        }

        if ($b->block_type === 'app_download_banner') {
            $cfg = $b->config ?? [];
            $data['title']            = ['ar' => $cfg['title_ar'] ?? null, 'en' => $cfg['title_en'] ?? null];
            $data['subtitle']         = ['ar' => $cfg['subtitle_ar'] ?? null, 'en' => $cfg['subtitle_en'] ?? null];
            $data['background_color'] = $cfg['background_color'] ?? '#FEE200';
            $data['app_store_url']    = $cfg['app_store_url'] ?? null;
            $data['play_store_url']   = $cfg['play_store_url'] ?? null;
            $data['phone_mockup_url'] = $cfg['phone_mockup_url'] ?? null;
        }

        if (empty($data['products']) && in_array($b->block_type, ['product_row', 'flash_sale', 'deal_of_day'], true)) {
            $dynamicProducts = $this->resolveDynamicProducts($b, $country);
            if ($dynamicProducts !== null) {
                $data['products'] = $dynamicProducts;
            }
        }

        return $data;
    }

    /**
     * Resolve config['source'] (best_sellers/new_arrivals/top_rated/trending/category/
     * flash_sale/personalized) into an actual, buy-box-ordered product list. Returns
     * null when the block has no dynamic source to resolve (e.g. source=manual, which
     * is already covered by the eager-loaded blockProducts pivot above).
     *
     * Listings are resolved via ListingQueryService::getBuyBoxForProducts(), which
     * considers every vendor type (express_fbn/merchant_fbp/marketplace) and only
     * prioritizes admin (express_fbn) listings — it never excludes vendor listings.
     */
    private function resolveDynamicProducts(PageBlock $b, Country $country): ?array
    {
        $config = $b->config ?? [];

        if ($b->block_type === 'flash_sale') {
            return $this->productsFromFlashSale(
                $config['flash_sale_id'] ?? null,
                $country,
                max(1, (int) ($config['max_items_shown'] ?? 8)),
            );
        }

        $source = $config['source'] ?? 'best_sellers';

        if ($source === 'manual') {
            return null;
        }

        $maxProducts = max(1, min(50, (int) ($config['max_products'] ?? $config['max_items_shown'] ?? ($b->block_type === 'deal_of_day' ? 8 : 12))));

        if ($source === 'flash_sale') {
            return $this->productsFromFlashSale($config['flash_sale_id'] ?? null, $country, $maxProducts);
        }

        $query = Product::query()
            ->where('status', 'active')
            ->whereNotIn('id', ProductCountrySetting::where('country_id', $country->id)
                ->where('is_available', false)
                ->pluck('product_id'))
            ->with(['variants', 'images']);

        if (!empty($config['category_id'])) {
            $rootCat = Category::find($config['category_id']);
            $categoryIds = $rootCat
                ? app(\App\Services\Customer\CategoryService::class)->getDescendantIds($rootCat)
                : [$config['category_id']];
            $query->whereIn('category_id', $categoryIds);
        }

        match ($source) {
            'new_arrivals' => $query->orderByDesc('published_at')->orderByDesc('created_at'),
            'top_rated' => $query->orderByRating(),
            'trending' => $query->orderByDesc('view_count')->orderByDesc('total_sold'),
            'category' => $query->orderByDesc('total_sold'),
            // No personalization engine yet; fall back to a rating/sales blend.
            'personalized' => $query->orderByRating()->orderByDesc('total_sold'),
            default => $query->orderByDesc('total_sold'), // best_sellers
        };

        $products = $query->limit($maxProducts)->get();

        return $this->productsToCards($products, $country);
    }

    private function productsToCards(Collection $products, Country $country): array
    {
        $buyBox = $this->unifiedQuery->getBuyBoxForProducts($products, $country);
        \App\Services\Customer\PromoBadgeResolver::instance()->prime(
            $products->map(fn (Product $p) => isset($buyBox[$p->id]) ? [\App\Services\Customer\PromoBadgeResolver::typeOf($buyBox[$p->id]), $buyBox[$p->id]->id, $p->id] : null)->filter()->values()
        );

        return $products
            ->map(fn (Product $p) => [$p, $buyBox[$p->id] ?? null])
            ->filter(fn (array $pair) => $pair[1] !== null)
            ->map(fn (array $pair) => $this->listingQuery->toMixedCardShape(
                $pair[1],
                $pair[0],
                $country,
            ))
            ->values()
            ->all();
    }

    private function productsFromFlashSale(?string $flashSaleId, Country $country, int $maxProducts): array
    {
        $countryCheck = function ($q) use ($country) {
            return $q->where('country_id', $country->id)->orWhereNull('country_id');
        };

        $sale = $flashSaleId
            ? FlashSale::where('id', $flashSaleId)->where($countryCheck)->first()
            : FlashSale::where($countryCheck)
                ->where('status', 'live')
                ->where('sale_starts_at', '<=', now())
                ->where('sale_ends_at', '>', now())
                ->orderBy('sale_ends_at')
                ->first();

        if (!$sale) {
            return [];
        }

        $submissions = $sale->submissions()
            ->whereIn('status', ['live', 'approved'])
            ->with([
                'vendorListing.vendor',
                'vendorListing.primaryShippingMethod',
                'vendorListing.productVariant.product.images',
            ])
            ->get()
            ->filter(fn($s) => $s->vendorListing && $s->vendorListing->productVariant?->product);

        \App\Services\Customer\PromoBadgeResolver::instance()->prime(\App\Services\Customer\PromoBadgeResolver::tuplesForListings($submissions->pluck('vendorListing')));

        return $submissions
            ->sortBy(fn ($s) => self::BUY_BOX_ORDER[$s->vendorListing->global_system_type->value] ?? 3)
            ->take($maxProducts)
            ->map(fn ($s) => $this->listingQuery->toMixedCardShape(
                $s->vendorListing,
                $s->vendorListing->productVariant->product,
                $country,
            ))
            ->values()
            ->all();
    }

    /**
     * Batched check: which of the given product ids currently belong to an
     * active, visible `mega_deals` page block for this country.
     *
     * "Active" mirrors buildSkeleton()'s block-visibility filter above
     * exactly (is_visible, visible_from/visible_until, country_override) —
     * intentionally NOT re-implemented, so this stays in lockstep with
     * whatever a customer would actually see rendered on the page. See
     * docs/plans/mega-deal-page-builder-correction.md Task F: this replaces
     * the removed flat `products.is_mega_deal` column, which was never
     * written to by the real (Page Builder) admin flow.
     *
     * One query for the candidate blocks + one join query for the matching
     * products — never per-product — same batching shape as
     * ProductQueryService::promoBadgesForProducts().
     *
     * @param  \Illuminate\Support\Collection<int,string>|array<int,string>  $productIds
     * @return \Illuminate\Support\Collection<int,string> subset of $productIds currently in an active mega_deals block
     */
    public function activeMegaDealProductIds($productIds, Country $country): Collection
    {
        $productIds = collect($productIds)->filter()->unique()->values();

        if ($productIds->isEmpty()) {
            return collect();
        }

        $now = now();

        $blockIds = PageBlock::where('block_type', 'mega_deals')
            ->where('is_visible', true)
            ->where(fn ($q) => $q->whereNull('visible_from')->orWhere('visible_from', '<=', $now))
            ->where(fn ($q) => $q->whereNull('visible_until')->orWhere('visible_until', '>', $now))
            ->where(fn ($q) => $q->whereNull('country_override')->orWhere('country_override', $country->id))
            ->pluck('id');

        if ($blockIds->isEmpty()) {
            return collect();
        }

        return \App\Models\PageBlockProduct::query()
            ->whereIn('page_block_id', $blockIds)
            ->join('product_variants', 'product_variants.id', '=', 'page_block_products.product_variant_id')
            ->whereIn('product_variants.product_id', $productIds)
            ->distinct()
            ->pluck('product_variants.product_id');
    }

    /**
     * Single-product convenience wrapper around activeMegaDealProductIds() —
     * used by the PDP detail resource path, which only ever needs one
     * product's worth of the same check.
     */
    public function isProductInActiveMegaDeal(string $productId, Country $country): bool
    {
        return $this->activeMegaDealProductIds([$productId], $country)->isNotEmpty();
    }

    /**
     * Detect mobile vs desktop from User-Agent header.
     */
    public function detectDevice(Request $request): string
    {
        $ua = strtolower($request->header('User-Agent', ''));

        if (str_contains($ua, 'mobile') || str_contains($ua, 'android') || str_contains($ua, 'iphone')) {
            return 'mobile';
        }

        return 'desktop';
    }
}
