<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\ProductListRequest;
use App\Http\Resources\Customer\ProductCardResource;
use App\Http\Resources\Customer\ProductDetailResource;
use App\Http\Responses\ApiResponse;
use App\Enums\AdminListingStatus;
use App\Models\AdminListing;
use App\Models\Category;
use App\Models\Country;
use App\Models\Product;
use App\Models\VendorListing;
use App\Models\Wishlist;
use App\Services\Customer\BuyBoxService;
use App\Services\Customer\CategoryService;
use App\Services\Customer\ListingQueryService;
use App\Services\Customer\ProductQueryService;
use App\Services\Customer\ProductViewService;
use App\Services\Customer\ReviewService;
use App\Services\Customer\SponsoredProductService;
use App\Services\BannerService;
use App\Services\Shared\PageBuilderService;
use App\Support\Concerns\BuildsProductAttributeSelector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    use BuildsProductAttributeSelector;

    public function __construct(
        private readonly ProductQueryService $products,
        private readonly ListingQueryService $listings,
        private readonly BuyBoxService $buyBox,
        private readonly ProductViewService $viewService,
        private readonly SponsoredProductService $sponsored,
        private readonly ReviewService $reviewService,
        private readonly \App\Services\Customer\ListingIdentifierService $identifiers,
        private readonly PageBuilderService $pageBuilder,
        private readonly BannerService $bannerService,
        private readonly \App\Services\Ads\PlacementAdService $placementAds,
    ) {
    }

    public function index(ProductListRequest $request, $country): JsonResponse
    {
        $country = $request->attributes->get('country');
        $filters = $request->validated();
        $perPage = (int) ($filters['per_page'] ?? 20);
        $page    = (int) ($filters['page'] ?? 1);

        $categoryService = app(CategoryService::class);

        // ── Resolve the 'category' filter, which may be a category slug/id or a
        // custom page slug (an aggregate landing page spanning several categories).
        $resolvedSlug = !empty($filters['category']) ? $categoryService->resolveSlug($filters['category']) : null;
        $category     = $resolvedSlug && $resolvedSlug['type'] === 'category' ? $resolvedSlug['model'] : null;
        $customPage   = $resolvedSlug && $resolvedSlug['type'] === 'custom_page' ? $resolvedSlug['model'] : null;

        $categoryIds = !empty($filters['category'])
            ? $categoryService->getCategoryIdsForFilter($filters['category'])
            : null;

        // ── Device & audience (same logic as HomeController) ─────────────────
        $deviceTarget = $this->pageBuilder->detectDevice($request);
        $audience     = auth('customer')->check() ? 'authenticated' : 'guest';

        // ── Admin listings (always first) ────────────────────────────────────────
        $adminBuilder = AdminListing::query()
            ->join('product_variants as pv', 'pv.id', '=', 'admin_listings.product_variant_id')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->where('admin_listings.country_id', $country->id)
            ->where('admin_listings.status', AdminListingStatus::Active->value)
            ->whereNull('admin_listings.deleted_at')
            ->where('p.status', 'active')
            ->whereNull('p.deleted_at')
            ->select('admin_listings.*')
            ->with([
                'productVariant:id,sku,slug,variant_name,variant_name_ar,product_id',
                'productVariant.images' => fn ($q) => $q->select('id', 'product_variant_id', 'product_id', 'path', 'disk', 'alt_text_en', 'alt_text_ar', 'position', 'is_primary')->orderBy('position')->limit(1),
                'productVariant.product.images' => fn ($q) => $q->select('id', 'product_variant_id', 'product_id', 'path', 'disk', 'alt_text_en', 'alt_text_ar', 'position', 'is_primary')->orderBy('position')->limit(1),
                'productVariant.product.category:id,name_en,name_ar,slug',
                'productVariant.product.customAttributes',
                'primaryShippingMethod:id,badge_label_en,badge_label_ar,badge_color_hex,badge_text_color_hex,badge_image_path,min_delivery_days,max_delivery_days,is_express_type',
            ]);

        // Apply category filter to admin listings if requested (includes all descendants,
        // or the union of descendants across every category linked to a custom page)
        if ($categoryIds !== null) {
            $adminBuilder->whereIn('p.category_id', $categoryIds);
        }
        if (!empty($filters['price_min'])) {
            $adminBuilder->where('admin_listings.price', '>=', (int) $filters['price_min']);
        }
        if (!empty($filters['price_max'])) {
            $adminBuilder->where('admin_listings.price', '<=', (int) $filters['price_max']);
        }

        $adminListings = $adminBuilder->orderBy('admin_listings.search_boost', 'desc')->orderBy('admin_listings.price')->get();

        // ── Vendor listings ───────────────────────────────────────────────────────
        $vendorBuilder = VendorListing::query()
            ->join('product_variants as pv', 'pv.id', '=', 'vendor_listings.product_variant_id')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->join('vendors as v', 'v.id', '=', 'vendor_listings.vendor_id')
            ->where('vendor_listings.country_id', $country->id)
            ->where('vendor_listings.status', 'active')
            ->whereNull('vendor_listings.deleted_at')
            ->where('p.status', 'active')
            ->whereNull('p.deleted_at')
            ->where('v.global_status', 'active')
            ->select('vendor_listings.*')
            ->with([
                'vendor:id,store_name,store_rating_avg',
                'productVariant:id,sku,slug,variant_name,variant_name_ar,product_id',
                'productVariant.images' => fn ($q) => $q->select('id', 'product_variant_id', 'product_id', 'path', 'disk', 'alt_text_en', 'alt_text_ar', 'position', 'is_primary')->orderBy('position')->limit(1),
                'productVariant.product.images' => fn ($q) => $q->select('id', 'product_variant_id', 'product_id', 'path', 'disk', 'alt_text_en', 'alt_text_ar', 'position', 'is_primary')->orderBy('position')->limit(1),
                'productVariant.product.category:id,name_en,name_ar,slug',
                'productVariant.product.customAttributes',
                'primaryShippingMethod:id,badge_label_en,badge_label_ar,badge_color_hex,badge_text_color_hex,badge_image_path,min_delivery_days,max_delivery_days,is_express_type',
            ]);

        $vendorBuilder = $this->listings->applyFilters($vendorBuilder, $filters, $categoryIds);
        $vendorBuilder = $this->listings->applySort($vendorBuilder, $filters['sort'] ?? 'relevance');
        $paginator     = $vendorBuilder->paginate($perPage);

        $wishlistIds = $this->listings->wishlistListingIds(auth('customer')->id());

        // ── Admin cards (deduplicated against each other by product_variant_id) ──
        $seenVariantIds = [];
        $adminItems     = [];
        foreach ($adminListings as $al) {
            $variantId = $al->product_variant_id;
            if (isset($seenVariantIds[$variantId])) {
                continue;
            }
            $seenVariantIds[$variantId] = true;
            $adminItems[] = $this->listings->toAdminCardShape(
                $al,
                $al->productVariant->product,
                $country,
                in_array($al->id, $wishlistIds),
            );
        }

        // ── Vendor cards ──────────────────────────────────────────────────────────
        $vendorItems = [];
        foreach ($paginator as $listing) {
            $vendorItems[] = $this->listings->toCardShape(
                listing: $listing,
                product: $listing->productVariant->product,
                country: $country,
                isWishlisted: in_array($listing->id, $wishlistIds),
                isSponsored: false,
            );
        }

        // Merge: admin first, then vendor
        $items = array_merge($adminItems, $vendorItems);
        $items = $this->sponsored->inject($items, $country, $page, 'category_top');

        $facets = $this->products->facets($country, $filters, $categoryIds);

        // ── Page builder (category/custom-page > brand priority) ───────────────
        $pageBuilder    = $this->resolvePageBuilder($country, $filters, $category, $customPage, $deviceTarget, $audience);
        $hasPageBuilder = $pageBuilder !== null;

        $pageEntity = $category ?? $customPage;
        $pageSlug   = $category?->slug ?? $customPage?->slugRecord?->slug_url;

        return ApiResponse::success([
            'items'  => ProductCardResource::collection(collect($items)),
            'facets' => $facets,
            'meta'   => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total() + count($adminItems),
            ],
            'page_builder'     => $pageBuilder,
            'has_page_builder' => $hasPageBuilder,
            'category'         => $pageEntity ? [
                'id'          => $pageEntity->id,
                'name'        => ['en' => $pageEntity->name_en, 'ar' => $pageEntity->name_ar],
                'slug'        => $pageSlug,
                'image_url'   => $pageEntity->image_url,
                'has_filters' => (bool) $pageEntity->has_filters,
            ] : null,
        ]);
    }

    /**
     * Resolve the page_builder for a product listing/search response.
     *
     * Priority: category/custom-page (high) > brand (low). Returns null when
     * no filter is present or no published page exists for the given reference.
     */
    private function resolvePageBuilder(
        Country $country,
        array $filters,
        ?Category $category,
        ?\App\Models\CustomPage $customPage,
        string $deviceTarget,
        string $audience,
    ): ?array {
        if ($customPage) {
            $result = $this->pageBuilder->resolve(
                country:      $country,
                pageType:     'custom_page',
                referenceId:  $customPage->id,
                deviceTarget: $deviceTarget,
                audience:     $audience,
            );
        } elseif ($category) {
            $result = $this->pageBuilder->resolve(
                country:      $country,
                pageType:     'category',
                referenceId:  $category->id,
                deviceTarget: $deviceTarget,
                audience:     $audience,
            );
        } elseif (!empty($filters['brand'])) {
            $result = $this->pageBuilder->resolve(
                country:      $country,
                pageType:     'brand',
                referenceId:  $filters['brand'],
                deviceTarget: $deviceTarget,
                audience:     $audience,
            );
        } else {
            return null;
        }

        if ($result === null || (empty($result['sections']) && empty($result['blocks']))) {
            return null;
        }

        return $result;
    }

    public function show(Request $request, $country, string $slug): JsonResponse
    {
        $country = $request->attributes->get('country');

        $product = Product::where('slug', $slug)
            ->where('status', 'active')
            ->whereHas(
                'countrySettings',
                fn($q) => $q
                    ->where('country_id', $country->id)
                    ->where('is_available', true)
            )
            ->with([
                'brand',
                'category',
                'images',
                'promoBadges',
                'variants.variantAttributes.attribute',
                'variants.variantAttributes.attributeValue',
                'variants.images',
                'countrySettings' => fn($q) => $q->where('country_id', $country->id),
            ])
            ->firstOrFail();

        $listings = $this->buyBox->getListings($product, $country);
        $product->setRelation('activeListings', $listings);

        $reviews = $product->reviews()
            ->where('status', 'published')
            ->with([
                'vendorReply',
                'customer:id,name',
                'files',
                'vendorListing.vendor:id,store_name',
                'vendorListing.productVariant.variantAttributes.attribute',
                'vendorListing.productVariant.variantAttributes.attributeValue',
            ])
            ->orderByDesc('helpful_count')
            ->limit(5)
            ->get();
        $product->setRelation('topReviews', $reviews);

        $buyBoxPrice = $listings->first()?->price;
        $relatedItems = ProductListResource::collection($this->relatedProducts($product, $country, $buyBoxPrice))->resolve();
        $relatedItems = $this->sponsored->inject(
            items: $relatedItems,
            country: $country,
            page: 1,
            placement: 'also_viewed',
            categoryIds: [$product->category_id],
            slots: [1, 4],
        );
        // inject() splices sponsored items shaped by ListingQueryService::toCardShape(),
        // which uses different keys than ProductListResource — reshape those (tagged via
        // '_sponsored_listing_id') to match the rest of the also-viewed list.
        $relatedItems = array_map(
            fn (array $item) => array_key_exists('_sponsored_listing_id', $item)
                ? $this->sponsoredToRelatedShape($item)
                : $item,
            $relatedItems,
        );
        $product->setRelation('related', $relatedItems);

        $isWishlisted = false;
        if (($customerId = auth('customer')->id()) && ($buyBoxListing = $listings->first())) {
            $isWishlisted = in_array($buyBoxListing->id, $this->listings->wishlistListingIds($customerId), true);
        }

        $this->viewService->logView(
            product: $product,
            country: $country,
            customerId: auth('customer')->id(),
            sessionId: $request->hasSession() ? $request->session()->getId() : '',
            source: $request->query('source', 'direct'),
            referrerUrl: $request->header('Referer'),
        );

        $selectedVariant = $listings->first()?->productVariant
            ?? $product->variants->firstWhere('is_default', true)
            ?? $product->variants->first();

        $listingsByVariant = $listings
            ->groupBy('product_variant_id')
            ->map(fn($group) => [
                'listing_id' => $group->first()->id,
                'listing_ref' => $this->identifiers->buildListingRef($group->first()),
            ])
            ->all();

        $audience = auth('customer')->check() ? 'logged_in' : 'guest';
        $sessionId = $request->header('X-Session-Id') ?? $request->cookie('session_id') ?? ($request->hasSession() ? $request->session()->getId() : null);
        $banner = $this->placementAds->resolve('product_page_bottom', $country, $audience, $sessionId, $product->id, $product->category_id);
        $crossSellAd = $this->sponsored->forProductPage(
            country:          $country,
            categoryId:       $product->category_id,
            excludeProductId: $product->id,
            customerId:       auth('customer')->id(),
            sessionId:        $sessionId,
        );

        $inlineBanner1 = $this->placementAds->resolve('product_page_inline_1', $country, $audience, $sessionId, $product->id, $product->category_id);
        $inlineBanner2 = $this->placementAds->resolve('product_page_inline_2', $country, $audience, $sessionId, $product->id, $product->category_id);

        $resource = new ProductDetailResource($product);
        $resource->isWishlisted = $isWishlisted;
        $resource->isMegaDeal = $this->pageBuilder->isProductInActiveMegaDeal($product->id, $country);
        $resource->banner = $banner;
        $resource->crossSellAd = $crossSellAd;
        $resource->inlineBanner1 = $inlineBanner1;
        $resource->inlineBanner2 = $inlineBanner2;
        $resource->ratingBreakdown = $this->reviewService->ratingBreakdown($product);
        $resource->productAttributes = $selectedVariant
            ? $this->productAttributesShape($product->variants, $selectedVariant, $listingsByVariant)
            : [];

        return ApiResponse::success($resource->toArray($request));
    }

    /**
     * Reshapes a sponsored item (ListingQueryService::toCardShape output) into the
     * same key schema ProductListResource produces, so the "also viewed" list stays
     * homogeneous after sponsored injection.
     */
    private function sponsoredToRelatedShape(array $item): array
    {
        return [
            'id'                  => $item['product_id'],
            'listing_id'          => $item['listing_id'],
            'listing_type'        => $item['listing_type'],
            'variant_id'          => $item['variant_id'],
            'product_slug'        => $item['product_slug'],
            'slug'                => $item['slug'],
            'variant_slug'        => $item['variant_slug'],
            'variant_name'        => $item['variant_name']['en'] ?? null,
            'variant_image'       => $item['variant_image'],
            'product_url'         => $item['product_url'],
            'name'                => ['en' => $item['name_en'], 'ar' => $item['name_ar']],
            'primary_image'       => $item['primary_image'],
            'images'              => $item['images'],
            'price_range'         => ['min' => $item['price'], 'max' => $item['price']],
            'category_name'       => $item['category_name'],
            'compare_at_price'    => $item['compare_at_price'],
            'rating_avg'          => (float) $item['rating_avg'],
            'rating_count'        => (int) $item['rating_count'],
            'seller_count'        => 1,
            'admin_listing_count' => $item['is_admin_listing'] ? 1 : 0,
            'total_seller_count'  => 1,
            'is_in_stock'         => true,
            'is_sponsored'        => true,
            'is_wishlisted'       => (bool) $item['is_wishlisted'],
            'shipping_badge'      => $item['shipping_badge'],
        ];
    }

    private function relatedProducts(Product $product, $country, ?int $buyBoxPrice): \Illuminate\Database\Eloquent\Collection
    {
        $query = Product::where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('status', 'active')
            ->whereHas(
                'countrySettings',
                fn($q) => $q
                    ->where('country_id', $country->id)
                    ->where('is_available', true)
            )
            ->with('images');

        if ($buyBoxPrice) {
            $low = (int) ($buyBoxPrice * 0.7);
            $high = (int) ($buyBoxPrice * 1.3);
            $query->whereHas(
                'variants.vendorListings',
                fn($q) => $q
                    ->where('country_id', $country->id)
                    ->where('status', 'active')
                    ->whereBetween('price', [$low, $high])
            );
        }

        return $query->orderByRating()->limit(8)->get();
    }
}
