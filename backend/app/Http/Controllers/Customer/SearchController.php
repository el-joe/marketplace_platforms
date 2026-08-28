<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\SearchRequest;
use App\Http\Responses\ApiResponse;
use App\Models\AdminListing;
use App\Models\Country;
use App\Services\Customer\ListingQueryService;
use App\Services\Customer\SearchService;
use App\Services\Customer\SponsoredProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(
        private readonly SearchService $search,
        private readonly SponsoredProductService $sponsored,
        private readonly ListingQueryService $listings,
    ) {
    }

    public function search(SearchRequest $request, $country): JsonResponse
    {
        $country = $request->attributes->get('country');

        $data = $request->validated();
        $perPage = (int) ($data['per_page'] ?? 20);
        $page = (int) ($data['page'] ?? 1);
        // Absent source_type must preserve pre-existing product-only search behavior;
        // 'all' only runs when explicitly requested.
        $sourceType = $data['source_type'] ?? 'product';

        if ($sourceType === 'all') {
            return $this->searchAll($request, $country, $data);
        }

        if ($sourceType === 'classified') {
            return $this->searchClassified($data, $perPage, $country);
        }

        if ($sourceType === 'travel') {
            return $this->searchTravel($data, $perPage, $country);
        }

        $result = $this->search->search(
            country: $country,
            query: $data['q'],
            filters: $data,
            perPage: $perPage,
            customerId: auth('customer')->id(),
            sessionId: $request->hasSession() ? $request->session()->getId() : '',
        );

        $paginator = $result['paginator'];
        $items = $result['items'];

        $items = $this->sponsored->inject($items, $country, $page, 'search_results', $data['q']);

        $facets = $this->search->facets($country, $data);

        return ApiResponse::success([
            'items' => $items,
            'facets' => $facets,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    private function searchClassified(array $data, int $perPage, Country $country): JsonResponse
    {
        $paginator = $this->search->searchClassifieds($data['q'], $data, $perPage);

        dispatch(new \App\Jobs\LogSearchJob(
            query: $data['q'],
            countryId: $country->id,
            resultsCount: $paginator->total(),
            filters: $data,
            customerId: auth('customer')->id(),
            sessionId: '',
            language: app()->getLocale(),
        ))->afterResponse();

        $items = $paginator->getCollection()
            ->map(fn($listing) => $this->listings->toClassifiedCardShape($listing))
            ->toArray();

        return ApiResponse::success([
            'source_type' => 'classified',
            'items' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'query' => $data['q'],
            ],
        ]);
    }

    private function searchTravel(array $data, int $perPage, Country $country): JsonResponse
    {
        $paginator = $this->search->searchTravel($data['q'], $perPage);

        dispatch(new \App\Jobs\LogSearchJob(
            query: $data['q'],
            countryId: $country->id,
            resultsCount: $paginator->total(),
            filters: $data,
            customerId: auth('customer')->id(),
            sessionId: '',
            language: app()->getLocale(),
        ))->afterResponse();

        $items = $paginator->getCollection()
            ->map(fn($package) => $this->listings->toTravelCardShape($package))
            ->toArray();

        return ApiResponse::success([
            'source_type' => 'travel',
            'items' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'query' => $data['q'],
            ],
        ]);
    }

    private function searchAll(SearchRequest $request, $country, array $data): JsonResponse
    {
        $country = $request->attributes->get('country');

        $query = $data['q'];

        $productResult = $this->search->search(
            country: $country,
            query: $query,
            filters: $data,
            perPage: 4,
            customerId: auth('customer')->id(),
            sessionId: $request->hasSession() ? $request->session()->getId() : '',
        );

        $productPaginator = $productResult['paginator'];
        $productItems = $productResult['items'];

        $wishlistIds = $this->listings->wishlistListingIds(auth('customer')->id());

        // Inject admin listings into product results (admin first, deduped by product_id)
        $adminSearchListings = AdminListing::where('country_id', $country->id)
            ->where('status', 'active')
            ->whereHas('productVariant.product', fn ($q) =>
                $q->where('status', 'active')
                  ->where(fn ($q2) => $q2->where('name_en', 'like', "%{$query}%")
                                         ->orWhere('name_ar', 'like', "%{$query}%"))
            )
            ->with(['productVariant.product.images', 'productVariant.images', 'primaryShippingMethod'])
            ->orderBy('search_boost', 'desc')->limit(4)->get();

        $seenProductIds = array_column($productItems, 'product_id');
        $adminResults   = $adminSearchListings->map(function ($al) use ($country, $wishlistIds) {
            return $this->listings->toAdminCardShape($al, $al->productVariant->product, $country,
                in_array($al->id, $wishlistIds));
        })->filter(fn ($i) => !in_array($i['product_id'], $seenProductIds))->values()->all();

        $productItems = array_merge($adminResults, $productItems);

        $classifiedPaginator = $this->search->searchClassifieds($query, $data, 4);
        $classifiedItems = $classifiedPaginator->getCollection()
            ->map(fn($listing) => $this->listings->toClassifiedCardShape($listing))
            ->toArray();

        $travelPaginator = $this->search->searchTravel($query, 4);
        $travelItems = $travelPaginator->getCollection()
            ->map(fn($package) => $this->listings->toTravelCardShape($package))
            ->toArray();

        $totalResults = $productPaginator->total() + $classifiedPaginator->total() + $travelPaginator->total();

        return ApiResponse::success([
            'source_type' => 'all',
            'products' => [
                'items' => array_slice($productItems, 0, 4),
                'total' => $productPaginator->total(),
            ],
            'classifieds' => [
                'items' => $classifiedItems,
                'total' => $classifiedPaginator->total(),
            ],
            'travel' => [
                'items' => $travelItems,
                'total' => $travelPaginator->total(),
            ],
            'meta' => [
                'query' => $query,
                'total_results' => $totalResults,
            ],
        ]);
    }

    public function suggestions(Request $request, $country): JsonResponse
    {
        $country = $request->attributes->get('country');

        $request->validate(['q' => ['nullable', 'string', 'max:255']]);

        $q = trim($request->query('q', ''));

        if (strlen($q) < 2) {
            return ApiResponse::success([
                'trending' => $this->search->trendingKeywords($country),
                'queries' => [],
                'products' => [],
                'categories' => [],
                'vendors' => [],
            ]);
        }

        $results = $this->search->suggestions($country, $q);

        return ApiResponse::success($results);
    }
}
