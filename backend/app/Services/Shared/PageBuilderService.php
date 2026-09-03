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
use App\Models\PaidBannerBooking;
use App\Models\Product;
use App\Models\ProductCountrySetting;
use App\Services\Customer\ListingQueryService;
use App\Support\Bilingual;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PageBuilderService
{
    private const BUY_BOX_ORDER = [
        'express_fbn' => 0,
        'merchant_fbp' => 1,
        'marketplace' => 2,
    ];

    public function __construct(
        private readonly ListingQueryService $listingQuery,
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
                'blockSellers' => fn($q) => $q->orderBy('position'),
                'blockSellers.seller',
                'blockCategories' => fn($q) => $q->orderBy('position'),
                'blockCategories.category',
                'blockBrands' => fn($q) => $q->orderBy('position'),
                'blockBrands.brand',
            ])
            ->get();

        $bookings = PaidBannerBooking::whereIn('page_block_id', $blocks->pluck('id'))
            ->where('status', 'active')
            ->where('booked_from', '<=', $now)
            ->where('booked_until', '>=', $now)
            ->get()
            ->keyBy('page_block_id');

        $sections = PageSection::where('page_id', $page->id)
            ->where('is_visible', true)
            ->orderBy('position')
            ->get();

        $blocksBySection = $blocks->groupBy('section_id');

        $sectionsData = $sections->map(function ($section) use ($blocksBySection, $bookings, $country) {
            $sectionBlocks = $blocksBySection->get($section->id, collect());

            // For column-layout sections, group blocks by column_index
            if ($section->layout === 'columns') {
                $columns = collect($sectionBlocks)
                    ->groupBy('column_index')
                    ->sortKeys()
                    ->map(fn ($colBlocks) =>
                        $colBlocks->map(fn (PageBlock $b) =>
                            $this->hydrateBlock($b, $bookings->get($b->id), $country)
                        )->values()->all()
                    )->values()->all();

                return [
                    'id'                   => $section->id,
                    'name'                 => $section->name,
                    'position'             => $section->position,
                    'layout'               => 'columns',
                    'columns_config'       => is_string($section->columns_config)
                        ? json_decode($section->columns_config, true)
                        : $section->columns_config,
                    'background_color'     => $section->background_color,
                    'background_image_url' => $section->background_image_url,
                    'background_image_type' => $section->background_image_type ?? 'section',
                    'columns'              => $columns,   // array of column arrays
                    'blocks'               => [],         // empty for column-layout sections
                ];
            }

            // Default: stack layout
            return [
                'id'               => $section->id,
                'name'             => $section->name,
                'position'         => $section->position,
                'layout'           => 'stack',
                'columns_config'   => is_string($section->columns_config)
                    ? json_decode($section->columns_config, true)
                    : $section->columns_config,
                'background_color'     => $section->background_color,
                'background_image_url' => $section->background_image_url,
                'background_image_type' => $section->background_image_type ?? 'section',
                'columns'          => [],
                'blocks'           => $sectionBlocks
                    ->map(fn (PageBlock $b) => $this->hydrateBlock($b, $bookings->get($b->id), $country))
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
                ->map(fn (PageBlock $b) => $this->hydrateBlock($b, $bookings->get($b->id), $country))
                ->values()
                ->all(),
            'has_sections'      => count($sectionsData) > 0,
            'total_block_count' => $blocks->count(),
        ];
    }

    /**
     * Attach whichever block-specific relations have data (slides, ad images,
     * products, sellers, categories, paid banner) alongside the base block fields.
     */
    private function hydrateBlock(PageBlock $b, ?PaidBannerBooking $booking, Country $country): array
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
            $banner = Banner::with('files')->find($b->config['banner_id']);

            if ($banner) {
                $desktopImage = $banner->files->firstWhere('file_type', 'banner_desktop');
                $mobileImage  = $banner->files->firstWhere('file_type', 'banner_mobile');

                if ($desktopImage) {
                    $data['banner'] = [
                        'image_url'           => $desktopImage->full_path,
                        'mobile_image_url'    => $mobileImage?->full_path,
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
                'desktop_url' => $s->desktop_url,
                'mobile_url' => $s->mobile_url,
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
                'url' => $i->file_url,
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
            $data['products'] = $b->blockProducts
                ->filter(fn ($bp) => $bp->productVariant?->product !== null)
                ->map(function ($bp) use ($country) {
                    $variantId = $bp->productVariant->id;
                    $product   = $bp->productVariant->product;

                    // Admin listing wins over vendor listing — use getForVariant() which
                    // now checks AdminListing first (from ListingQueryService fix).
                    $listing = $this->listingQuery->getForVariant($variantId, $country, 1)->first();

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
            $brands   = \App\Models\PageBlockBrand::where('page_block_id', $b->id)
                ->orderBy('position')
                ->limit($maxItems)
                ->with('brand')
                ->get();

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
            $data['products']        = $products;
        }

        if ($b->block_type === 'image_slider') {
            $cfg   = $b->config ?? [];
            $items = AdImageItem::where('page_block_id', $b->id)
                ->where('is_active', true)
                ->orderBy('position')
                ->get()
                ->map(fn ($img) => [
                    'image_url' => $img->file_url,
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
                'image_url'    => $tile['image_url'] ?? null,
                'image_url_ar' => $tile['image_url_ar'] ?? null,
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
                'image_url' => $img->file_url,
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

        if ($booking !== null) {
            $data['paid_banner'] = [
                'image_url' => $booking->image_url,
                'link_url' => $booking->link_url,
                'alt_text' => $booking->alt_text,
            ];
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
        $buyBox = $this->listingQuery->getBuyBoxForProducts($products, $country);

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
