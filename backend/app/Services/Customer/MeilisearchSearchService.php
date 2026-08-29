<?php

namespace App\Services\Customer;

use App\Models\ClassifiedListing;
use App\Models\Country;
use App\Models\TravelPackage;
use App\Models\VendorListing;
use Illuminate\Pagination\LengthAwarePaginator;

class MeilisearchSearchService
{
    public function __construct(
        private readonly ListingQueryService $listings,
    ) {
    }

    /**
     * Full listing search via Meilisearch. Returns the same shape as
     * SearchService::dbSearch(): ['items' => [...], 'paginator' => LengthAwarePaginator].
     */
    public function search(
        Country $country,
        string $query,
        array $filters = [],
        int $perPage = 20,
        int $page = 1,
        ?string $customerId = null,
        string $sessionId = '',
    ): array {
        $scoutBuilder = VendorListing::search($query)
            ->where('country_id', $country->id)
            ->where('status', 'active');

        if (!empty($filters['brand'])) {
            $scoutBuilder->where('brand_id', $filters['brand']);
        }
        if (!empty($filters['category'])) {
            $scoutBuilder->where('category_id', $filters['category']);
        }
        if (!empty($filters['condition'])) {
            $scoutBuilder->where('condition', $filters['condition']);
        }
        if (!empty($filters['fulfillment_model'])) {
            $scoutBuilder->where('fulfillment_model', $filters['fulfillment_model']);
        }
        if (!empty($filters['price_min'])) {
            $scoutBuilder->where('price', '>=', (int) $filters['price_min']);
        }
        if (!empty($filters['price_max'])) {
            $scoutBuilder->where('price', '<=', (int) $filters['price_max']);
        }
        if (!empty($filters['rating_min'])) {
            $scoutBuilder->where('rating_avg', '>=', (float) $filters['rating_min']);
        }

        $sort = $filters['sort'] ?? 'relevance';
        match ($sort) {
            'price_asc' => $scoutBuilder->orderBy('price', 'asc'),
            'price_desc' => $scoutBuilder->orderBy('price', 'desc'),
            'rating' => $scoutBuilder->orderBy('rating_avg', 'desc'),
            'newest' => $scoutBuilder->orderBy('created_at', 'desc'),
            'best_selling' => $scoutBuilder->orderBy('total_sold', 'desc'),
            default => null,
        };

        $paginator = $scoutBuilder->paginate($perPage, 'page', $page);

        $ids = $paginator->getCollection()->pluck('id')->all();

        $hydrated = VendorListing::whereIn('id', $ids)
            ->with([
                'vendor:id,store_name,store_rating_avg',
                'productVariant:id,sku,slug,variant_name,product_id',
                'productVariant.images',
                'productVariant.product.images',
                'productVariant.product.category:id,name_en,name_ar,slug',
                'productVariant.product.brand:id,name_en,name_ar,slug,logo_url',
                'primaryShippingMethod:id,badge_label_en,badge_label_ar,badge_color_hex,badge_text_color_hex,badge_image_path,min_delivery_days,max_delivery_days,is_express_type',
            ])
            ->get()
            ->keyBy('id');

        $wishlistIds = $this->listings->wishlistListingIds($customerId ?? auth('customer')->id());

        $items = collect($ids)
            ->map(function (string $id) use ($hydrated, $country, $wishlistIds) {
                $listing = $hydrated->get($id);
                if (!$listing) {
                    return null;
                }

                return $this->listings->toCardShape(
                    listing: $listing,
                    product: $listing->productVariant->product,
                    country: $country,
                    isWishlisted: in_array($id, $wishlistIds),
                    isSponsored: false,
                );
            })
            ->filter()
            ->values()
            ->all();

        $resultPaginator = new LengthAwarePaginator($items, $paginator->total(), $perPage, $page);

        return [
            'items' => $items,
            'paginator' => $resultPaginator,
        ];
    }

    public function searchClassifieds(
        string $query,
        array $filters = [],
        int $perPage = 20,
        int $page = 1,
    ): LengthAwarePaginator {
        $scoutBuilder = ClassifiedListing::search($query)
            ->where('status', 'active');

        if (!empty($filters['category'])) {
            $scoutBuilder->where('classified_category_id', $filters['category']);
        }
        if (!empty($filters['price_min'])) {
            $scoutBuilder->where('price', '>=', (int) $filters['price_min']);
        }
        if (!empty($filters['price_max'])) {
            $scoutBuilder->where('price', '<=', (int) $filters['price_max']);
        }

        $paginator = $scoutBuilder->paginate($perPage, 'page', $page);

        $ids = $paginator->getCollection()->pluck('id')->all();

        $hydrated = ClassifiedListing::whereIn('id', $ids)
            ->with(['images', 'classifiedCategory', 'city'])
            ->get()
            ->keyBy('id');

        $items = collect($ids)
            ->map(fn ($id) => $hydrated->get($id))
            ->filter()
            ->values();

        return new LengthAwarePaginator($items, $paginator->total(), $perPage, $page);
    }

    public function searchTravel(
        string $query,
        int $perPage = 20,
        int $page = 1,
    ): LengthAwarePaginator {
        $paginator = TravelPackage::search($query)
            ->where('status', 'active')
            ->orderBy('departure_date', 'desc')
            ->paginate($perPage, 'page', $page);

        $ids = $paginator->getCollection()->pluck('id')->all();

        $hydrated = TravelPackage::whereIn('id', $ids)
            ->with([
                'agency:id,name',
                'categories:id,name_en,name_ar,slug',
                'media' => fn ($q) => $q->orderBy('position'),
            ])
            ->get()
            ->keyBy('id');

        $items = collect($ids)
            ->map(fn ($id) => $hydrated->get($id))
            ->filter()
            ->values();

        return new LengthAwarePaginator($items, $paginator->total(), $perPage, $page);
    }

    /**
     * Product name autocomplete via Meilisearch. Returns the same shape as
     * SearchService::dbSuggestions()'s 'products' portion.
     */
    public function suggestions(Country $country, string $query): array
    {
        $scoutResults = VendorListing::search($query)
            ->where('country_id', $country->id)
            ->where('status', 'active')
            ->take(10)
            ->get();

        $ids = $scoutResults->pluck('id')->all();

        $hydrated = VendorListing::whereIn('id', $ids)
            ->with([
                'productVariant.product:id,name_en,name_ar,slug',
                'productVariant.images',
                'productVariant.product.images',
                'vendor:id,store_name',
            ])
            ->get()
            ->keyBy('id');

        $productSuggestions = collect($ids)
            ->map(function (string $id) use ($hydrated) {
                $listing = $hydrated->get($id);
                if (!$listing) {
                    return null;
                }

                $product = $listing->productVariant->product;
                $variant = $listing->productVariant;

                return [
                    'id' => $listing->id,
                    'product_id' => $product->id,
                    'slug' => $product->slug,
                    'name' => app()->getLocale() === 'ar' ? $product->name_ar : $product->name_en,
                    'vendor' => $listing->vendor->store_name,
                    'type' => 'product',
                    'primary_image' => $variant->images->first()?->url
                        ?? $product->images->first()?->url,
                ];
            })
            ->filter()
            ->values();

        return [
            'queries' => $productSuggestions->pluck('name')->filter()->unique()->values()->take(5)->all(),
            'products' => $productSuggestions->take(10)->all(),
        ];
    }
}
