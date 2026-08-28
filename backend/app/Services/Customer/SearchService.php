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
                'productVariant.images',
                'productVariant.product.images',
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
        return AdminListing::where('country_id', $country->id)
            ->where('status', AdminListingStatus::Active->value)
            ->whereHas('productVariant.product', function ($q) use ($query) {
                $q->where('status', 'active')
                  ->where(function ($q2) use ($query) {
                      $q2->where('name_en', 'like', "%{$query}%")
                         ->orWhere('name_ar', 'like', "%{$query}%");
                  });
            })
            ->with([
                'productVariant.product.images',
                'productVariant.images',
                'productVariant.variantAttributes.attribute',
                'productVariant.variantAttributes.attributeValue',
                'primaryShippingMethod',
            ])
            ->orderBy('search_boost', 'desc')
            ->orderBy('price', 'asc')
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
            ->when(!empty($filters['price_min']), fn($q) => $q->where('price', '>=', (int) ($filters['price_min'] * 100)))
            ->when(!empty($filters['price_max']), fn($q) => $q->where('price', '<=', (int) ($filters['price_max'] * 100)))
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
                'media' => fn($q) => $q->orderBy('position')->limit(1),
            ])
            ->orderByDesc('departure_date')
            ->paginate($perPage);
    }

    public function suggestions(Country $country, string $query): array
    {
        // Listing-centric, same as search(): one row per active vendor listing
        // matching the query, so a product sold by several vendors can surface
        // more than once here too.
        $listings = VendorListing::where('country_id', $country->id)
            ->where('status', 'active')
            ->whereHas('productVariant.product', function ($q) use ($query) {
                $q->where('status', 'active')
                    ->where(function ($q2) use ($query) {
                        $q2->where('name_en', 'like', "%{$query}%")
                            ->orWhere('name_ar', 'like', "%{$query}%");
                    });
            })
            ->whereHas('vendor', fn($q) => $q->where('global_status', 'active'))
            ->with(['productVariant.product:id,name_en,name_ar,slug', 'vendor:id,store_name'])
            ->limit(10)
            ->get();

        $productSuggestions = $listings->map(function ($listing) {
            $product = $listing->productVariant->product;

            return [
                'id' => $listing->id,
                'product_id' => $product->id,
                'slug' => $product->slug,
                'name' => app()->getLocale() === 'ar' ? $product->name_ar : $product->name_en,
                'vendor' => $listing->vendor->store_name,
                'type' => 'product',
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
