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
use App\Models\WishlistItem;
use App\Services\Customer\BuyBoxService;
use App\Services\Customer\CategoryService;
use App\Services\Customer\ListingQueryService;
use App\Services\Customer\ProductQueryService;
use App\Services\Customer\ProductViewService;
use App\Services\Customer\ReviewService;
use App\Services\Customer\SponsoredProductService;
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
    ) {
    }

    /**
     * Resolve a category filter value (id or slug) to its actual id.
     */
    private function resolveCategoryId(string $idOrSlug): ?string
    {
        return Category::where('id', $idOrSlug)
            ->orWhere('slug', $idOrSlug)
            ->value('id');
    }

    public function index(ProductListRequest $request, $country): JsonResponse
    {
        $country = $request->attributes->get('country');
        $filters = $request->validated();
        $perPage = (int) ($filters['per_page'] ?? 20);
        $page    = (int) ($filters['page'] ?? 1);

        if (!empty($filters['category'])) {
            $filters['category'] = $this->resolveCategoryId($filters['category']) ?? $filters['category'];
        }

        $category = !empty($filters['category']) ? Category::find($filters['category']) : null;

        $categoryData = null;
        if ($category) {
            $catVersion   = \Illuminate\Support\Facades\Cache::get("category_v:{$category->id}", 0);
            $categoryData = \Illuminate\Support\Facades\Cache::remember(
                "product_ctrl_category:{$category->id}:{$catVersion}",
                now()->addMinutes(30),
                fn () => [
                    'id'          => $category->id,
                    'name'        => ['en' => $category->name_en, 'ar' => $category->name_ar],
                    'slug'        => $category->slug,
                    'image_url'   => $category->image_url,
                    'has_filters' => (bool) $category->has_filters,
                ]
            );
        }

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
                'productVariant:id,sku,slug,variant_name,product_id',
                'productVariant.images' => fn ($q) => $q->select('id', 'product_variant_id', 'product_id', 'path', 'disk', 'alt_text_en', 'alt_text_ar', 'position', 'is_primary')->orderBy('position')->limit(1),
                'productVariant.product.images' => fn ($q) => $q->select('id', 'product_variant_id', 'product_id', 'path', 'disk', 'alt_text_en', 'alt_text_ar', 'position', 'is_primary')->orderBy('position')->limit(1),
                'productVariant.product.category:id,name_en,name_ar,slug',
                'primaryShippingMethod:id,badge_label_en,badge_label_ar,badge_color_hex,badge_text_color_hex,badge_image_path,min_delivery_days,max_delivery_days,is_express_type',
            ]);

        // Apply category filter to admin listings if requested (includes all descendants)
        if (!empty($filters['category'])) {
            $categoryIds = $category
                ? app(CategoryService::class)->getDescendantIds($category)
                : [$filters['category']];
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
                'productVariant:id,sku,slug,variant_name,product_id',
                'productVariant.images' => fn ($q) => $q->select('id', 'product_variant_id', 'product_id', 'path', 'disk', 'alt_text_en', 'alt_text_ar', 'position', 'is_primary')->orderBy('position')->limit(1),
                'productVariant.product.images' => fn ($q) => $q->select('id', 'product_variant_id', 'product_id', 'path', 'disk', 'alt_text_en', 'alt_text_ar', 'position', 'is_primary')->orderBy('position')->limit(1),
                'productVariant.product.category:id,name_en,name_ar,slug',
                'primaryShippingMethod:id,badge_label_en,badge_label_ar,badge_color_hex,badge_text_color_hex,badge_image_path,min_delivery_days,max_delivery_days,is_express_type',
            ]);

        $vendorBuilder = $this->listings->applyFilters($vendorBuilder, $filters);
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

        $facets = $this->products->facets($country, $filters);

        // ── Page builder (category > brand priority) ──────────────────────────
        $pageBuilder    = $this->resolvePageBuilder($country, $filters, $deviceTarget, $audience);
        $hasPageBuilder = $pageBuilder !== null;

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
            'category'         => $categoryData,
        ]);
    }

    /**
     * Resolve the page_builder for a product listing/search response.
     *
     * Priority: category (high) > brand (low). Returns null when no filter
     * is present or no published page exists for the given reference.
     */
    private function resolvePageBuilder(
        Country $country,
        array $filters,
        string $deviceTarget,
        string $audience,
    ): ?array {
        if (!empty($filters['category'])) {
            $result = $this->pageBuilder->resolve(
                country:      $country,
                pageType:     'category',
                referenceId:  $filters['category'],
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
                'variants.variantAttributes.attribute',
                'variants.variantAttributes.attributeValue',
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
        $product->setRelation('related', $this->relatedProducts($product, $country, $buyBoxPrice));

        $isWishlisted = false;
        if (($customerId = auth('customer')->id()) && ($buyBoxListing = $listings->first())) {
            $isWishlisted = WishlistItem::where('customer_id', $customerId)
                ->where('vendor_listing_id', $buyBoxListing->id)
                ->exists();
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

        $resource = new ProductDetailResource($product);
        $resource->isWishlisted = $isWishlisted;
        $resource->ratingBreakdown = $this->reviewService->ratingBreakdown($product);
        $resource->productAttributes = $selectedVariant
            ? $this->productAttributesShape($product->variants, $selectedVariant, $listingsByVariant)
            : [];

        return ApiResponse::success($resource->toArray($request));
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
