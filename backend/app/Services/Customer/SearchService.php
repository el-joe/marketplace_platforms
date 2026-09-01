<?php

namespace App\Services\Customer;

use App\Enums\AdminListingStatus;
use App\Models\AdminListing;
use App\Models\ClassifiedListing;
use App\Models\Country;
use App\Models\SearchSuggestion;
use App\Models\TravelPackage;
use App\Models\Vendor;
use App\Models\VendorListing;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class SearchService
{
    public function __construct(
        private readonly ProductQueryService $productQuery,
        private readonly ListingQueryService $listings,
        private readonly UnifiedCategoryService $unifiedCategories,
    ) {
    }

    /**
     * Listing-centric search: one row per active vendor listing matching the query,
     * so a product sold by several vendors appears once per vendor. Admin (platform
     * stock) listings matching the query are prepended so they always surface first.
     */
    public function search(
        Country $country,
        string $query,
        array $filters = [],
        int $perPage = 20,
        ?string $customerId = null,
        string $sessionId = '',
    ): array {

        $builder = $this->listings->baseSearchQuery($country, $query)
            ->with([
                'vendor:id,store_name,store_rating_avg',
                'productVariant:id,sku,slug,variant_name,product_id',
                'productVariant.images' => fn ($q) => $q->orderBy('position')->limit(1),
                'productVariant.product.images' => fn ($q) => $q->orderBy('position')->limit(1),
                'productVariant.product.category:id,name_en,name_ar,slug',
                'primaryShippingMethod:id,badge_label_en,badge_label_ar,badge_color_hex,badge_text_color_hex,badge_image_path,min_delivery_days,max_delivery_days,is_express_type',
            ]);

        $builder = $this->listings->applyFilters($builder, $filters);
        $builder = $this->listings->applySort($builder, $filters['sort'] ?? 'relevance');

        $paginator = $builder->paginate($perPage);

        dispatch(new \App\Jobs\LogSearchJob(
            query: $query,
            countryId: $country->id,
            resultsCount: $paginator->total(),
            filters: $filters,
            customerId: $customerId,
            sessionId: $sessionId,
            language: app()->getLocale(),
        ))->afterResponse();

        $wishlistIds = $this->listings->wishlistListingIds($customerId ?? auth('customer')->id());

        $items = [];
        foreach ($paginator as $listing) {
            $product = $listing->productVariant->product;

            $items[] = $this->listings->toCardShape(
                listing: $listing,
                product: $product,
                country: $country,
                isWishlisted: in_array($listing->id, $wishlistIds),
                isSponsored: false,
            );
        }

        // Prepend admin listings (platform stock always surfaces first).
        $adminListings = $this->adminListingSearch($country, $query, 4);

        $adminItems = $adminListings->map(function (AdminListing $al) use ($country, $wishlistIds) {
            $product = $al->productVariant->product;
            return $this->listings->toAdminCardShape($al, $product, $country,
                in_array($al->id, $wishlistIds));
        })->values()->all();

        // Deduplicate by product_id so the same product doesn't appear twice
        // if it has both an admin and vendor listing.
        $seenProductIds = array_column($adminItems, 'product_id');
        $vendorItems    = array_filter($items, fn ($i) => !in_array($i['product_id'], $seenProductIds));

        $items = array_merge($adminItems, array_values($vendorItems));

        return [
            'items' => $items,
            'paginator' => $paginator,
        ];
    }

    /**
     * Search admin_listings for the query — returns up to $limit active listings
     * in the given country whose product name matches the query.
     * Admin listings appear before vendor listings in all product search results.
     */
    private function adminListingSearch(Country $country, string $query, int $limit = 4): \Illuminate\Support\Collection
    {
        $builder = AdminListing::query()
            ->join('product_variants as pv', 'pv.id', '=', 'admin_listings.product_variant_id')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->where('admin_listings.country_id', $country->id)
            ->where('admin_listings.status', AdminListingStatus::Active->value)
            ->whereNull('admin_listings.deleted_at')
            ->where('p.status', 'active')
            ->whereNull('p.deleted_at')
            ->select('admin_listings.*');

        // Per-word AND search: each token must appear in at least one of the name columns.
        // Avoids FULLTEXT min-token-size issues with short numbers like "15", "S23", etc.
        $tokens = preg_split('/\s+/', mb_strtolower(trim($query)), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($tokens as $token) {
            $pattern = '%' . addcslashes($token, '%_\\') . '%';
            $builder->where(function ($q2) use ($pattern) {
                $q2->where('p.name_en', 'like', $pattern)
                    ->orWhere('p.name_ar', 'like', $pattern)
                    ->orWhere('p.short_desc_en', 'like', $pattern)
                    ->orWhere('p.model_number', 'like', $pattern);
            });
        }

        return $builder
            ->with([
                'productVariant.product.images' => fn ($q) => $q->orderBy('position')->limit(1),
                'productVariant.images' => fn ($q) => $q->orderBy('position')->limit(1),
                'productVariant.variantAttributes.attribute',
                'productVariant.variantAttributes.attributeValue',
                'primaryShippingMethod',
            ])
            ->orderBy('admin_listings.search_boost', 'desc')
            ->orderBy('admin_listings.price', 'asc')
            ->limit($limit)
            ->get();
    }

    public function searchClassifieds(string $query, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return ClassifiedListing::where('status', 'active')
            ->where(function ($q) use ($query) {
                $q->where('title_en', 'like', "%{$query}%")
                    ->orWhere('title_ar', 'like', "%{$query}%")
                    ->orWhere('description_en', 'like', "%{$query}%");
            })
            ->with(['images', 'classifiedCategory', 'city'])
            ->when(!empty($filters['category']), fn($q) => $q->where('classified_category_id', $filters['category']))
            ->when(!empty($filters['price_min']), fn($q) => $q->where('price', '>=', (int) $filters['price_min']))
            ->when(!empty($filters['price_max']), fn($q) => $q->where('price', '<=', (int) $filters['price_max']))
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function searchTravel(string $query, int $perPage = 20): LengthAwarePaginator
    {
        return TravelPackage::where('status', 'active')
            ->where(function ($q) use ($query) {
                $q->where('title_en', 'like', "%{$query}%")
                    ->orWhere('title_ar', 'like', "%{$query}%")
                    ->orWhere('destination_country', 'like', "%{$query}%")
                    ->orWhere('destination_city', 'like', "%{$query}%");
            })
            ->with([
                'agency:id,name',
                'categories:id,name_en,name_ar,slug',
                'media' => fn($q) => $q->orderBy('position'),
            ])
            ->orderByDesc('departure_date')
            ->paginate($perPage);
    }

    public function suggestions(Country $country, string $query): array
    {
        // Listing-centric, same as search(): one row per active vendor listing
        // matching the query, so a product sold by several vendors can surface
        // more than once here too.
        $prefix = Str::lower(trim($query)) . '%';

        $rows = \Illuminate\Support\Facades\DB::table('vendor_listings as vl')
            ->join('product_variants as pv', 'pv.id', '=', 'vl.product_variant_id')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->join('vendors as v', 'v.id', '=', 'vl.vendor_id')
            ->where('vl.country_id', $country->id)
            ->where('vl.status', 'active')
            ->whereNull('vl.deleted_at')
            ->where('p.status', 'active')
            ->whereNull('p.deleted_at')
            ->where('v.global_status', 'active')
            ->where(function ($q) use ($prefix, $query) {
                $contains = '%' . Str::lower(trim($query)) . '%';
                $q->whereRaw('LOWER(p.name_en) like ?', [$prefix])
                    ->orWhereRaw('LOWER(p.name_ar) like ?', [$prefix])
                    ->orWhereRaw('LOWER(p.name_en) like ?', [$contains])
                    ->orWhereRaw('LOWER(p.name_ar) like ?', [$contains]);
            })
            ->select([
                'vl.id as listing_id',
                'p.id as product_id',
                'p.slug',
                'p.name_en',
                'p.name_ar',
                'v.store_name',
            ])
            ->limit(10)
            ->get();

        $productIds = $rows->pluck('product_id')->unique()->values()->all();
        $images = \Illuminate\Support\Facades\DB::table('product_images')
            ->whereIn('product_id', $productIds)
            ->whereNull('product_variant_id')
            ->orderBy('position')
            ->get(['product_id', 'path', 'disk'])
            ->unique('product_id')
            ->keyBy('product_id');

        $productSuggestions = $rows->map(function ($row) use ($images) {
            $image = $images->get($row->product_id);

            return [
                'id' => $row->listing_id,
                'product_id' => $row->product_id,
                'slug' => $row->slug,
                'name' => app()->getLocale() === 'ar' ? $row->name_ar : $row->name_en,
                'vendor' => $row->store_name,
                'type' => 'product',
                'primary_image' => $image ? \Illuminate\Support\Facades\Storage::disk($image->disk)->url($image->path) : null,
            ];
        });

        $queries = $productSuggestions->pluck('name')->filter()->unique()->values()->take(5)->all();

        $vendors = Vendor::query()
            ->where('store_name', 'like', "%{$query}%")
            ->where('global_status', 'active')
            ->select('id', 'store_name', 'store_slug', 'store_rating_avg')
            ->limit(3)
            ->get()
            ->map(fn($vendor) => [
                'id' => $vendor->id,
                'store_name' => $vendor->store_name,
                'slug' => $vendor->store_slug,
                'rating' => $vendor->store_rating_avg,
            ]);

        $trending = SearchSuggestion::where('country_id', $country->id)
            ->where('is_blocked', false)
            ->where('keyword_normalized', 'like', Str::lower(trim($query)) . '%')
            ->orderByDesc('is_pinned')
            ->orderByDesc('search_count')
            ->limit(5)
            ->pluck('keyword')
            ->toArray();

        return [
            'trending' => $trending,
            'queries' => $queries,
            'products' => $productSuggestions->toArray(),
            'categories' => $this->unifiedCategories->search($query),
            'vendors' => $vendors->toArray(),
        ];
    }

    public function trendingKeywords(Country $country, int $limit = 10): array
    {
        return SearchSuggestion::where('country_id', $country->id)
            ->where('is_blocked', false)
            ->orderByDesc('is_pinned')
            ->orderByDesc('search_count')
            ->limit($limit)
            ->pluck('keyword')
            ->toArray();
    }

    // Delegates to ProductQueryService so /search and /products share one query implementation.

    public function listing(Country $country, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->productQuery->paginate($country, $filters, $perPage);
    }

    public function listingByCategories(
        Country $country,
        array $categoryIds,
        array $filters = [],
        int $perPage = 20,
    ): LengthAwarePaginator {
        return $this->productQuery->paginate($country, $filters, $perPage, $categoryIds);
    }

    public function facets(Country $country, array $filters = [], ?array $categoryIds = null): array
    {
        return $this->productQuery->facets($country, $filters, $categoryIds);
    }
}
