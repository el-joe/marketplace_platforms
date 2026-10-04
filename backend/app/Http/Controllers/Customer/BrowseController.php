<?php

namespace App\Http\Controllers\Customer;

use App\Enums\ClassifiedListingStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\ClassifiedBrowseCategoryResource;
use App\Http\Resources\Customer\ProductBrowseCategoryResource;
use App\Http\Resources\Customer\TravelBrowseCategoryResource;
use App\Http\Resources\Customer\TravelCategorySummaryResource;
use App\Models\Category;
use App\Models\ClassifiedCategory;
use App\Models\ClassifiedListing;
use App\Models\Country;
use App\Models\TravelCategory;
use App\Services\Customer\CategoryService;
use App\Services\Customer\ListingQueryService;
use App\Services\Customer\ProductQueryService;
use App\Services\Customer\UnifiedCategoryService;
use App\Services\Shared\PageBuilderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class BrowseController extends Controller
{
    public function __construct(
        private readonly CategoryService $categories,
        private readonly ProductQueryService $products,
        private readonly ListingQueryService $listings,
        private readonly UnifiedCategoryService $unifiedCategories,
        private readonly PageBuilderService $pageBuilder,
    ) {}

    /**
     * GET /api/customer/v1/{country}/browse/{type}/{id}
     */
    public function show(Request $request, $country, string $type, string $id): JsonResponse
    {
        $country = $request->attributes->get('country');

        $request->validate([
            'type' => [Rule::in(['product', 'classified', 'travel'])],
        ]);

        if (! in_array($type, ['product', 'classified', 'travel'], true)) {
            return response()->json(['success' => false, 'message' => __('common.exceptions.browse.invalid_type')], 404);
        }

        return match ($type) {
            'product' => $this->browseProduct($request, $country, $id),
            'classified' => $this->browseClassified($request, $country, $id),
            'travel' => $this->browseTravel($request, $country, $id),
        };
    }

    /**
     * GET /api/customer/v1/{country}/travel
     * Same as browse/travel/all — every active package, unfiltered by category.
     */
    public function travelIndex(Request $request, $country): JsonResponse
    {
        $country = $request->attributes->get('country');

        return $this->browseTravel($request, $country, 'all');
    }

    /**
     * GET /api/customer/v1/{country}/classified
     * Same as browse/classified/all — every active classified listing, unfiltered by category.
     */
    public function classifiedIndex(Request $request, $country): JsonResponse
    {
        $country = $request->attributes->get('country');

        return $this->browseClassified($request, $country, 'all');
    }

    /**
     * GET /api/customer/v1/{country}/classified/map-pins
     * Returns lightweight pin data for active classified listings with coordinates.
     */
    public function classifiedMapPins(Request $request, $country): JsonResponse
    {
        $query = ClassifiedListing::query()
            ->where('status', ClassifiedListingStatus::Active)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->with(['images' => fn ($q) => $q->where('is_primary', true)->limit(1)]);

        // Bounds filter
        if ($request->has('bounds')) {
            $bounds = $request->input('bounds');
            if (isset($bounds['south'], $bounds['north'])) {
                $query->whereBetween('latitude', [(float) $bounds['south'], (float) $bounds['north']]);
            }
            if (isset($bounds['east'], $bounds['west'])) {
                $query->whereBetween('longitude', [(float) $bounds['west'], (float) $bounds['east']]);
            }
        }

        if ($request->filled('category')) {
            $query->where('classified_category_id', $request->input('category'));
        }

        if ($request->filled('purpose')) {
            $query->where('listing_purpose', $request->input('purpose'));
        }

        if ($request->filled('city_id')) {
            $query->where('city_id', $request->input('city_id'));
        }

        if ($request->filled('country')) {
            $query->whereHas('country', fn ($q) => $q->where('iso2', strtoupper($request->input('country'))));
        }

        $locale = app()->getLocale();

        $pins = $query->limit(300)->get()->map(function (ClassifiedListing $listing) use ($locale): array {
            $primaryImage = $listing->images->first();
            $thumbnail = $primaryImage ? asset('storage/'.$primaryImage->file_path) : null;

            $title = $locale === 'ar' && ! empty($listing->title_ar)
                ? $listing->title_ar
                : ($listing->title_en ?? $listing->title_ar ?? '');

            return [
                'id' => $listing->id,
                'number' => $listing->listing_number,
                'slug' => $listing->slug,
                'title' => $title,
                'price' => (int) $listing->price,
                'currency' => $listing->currency,
                'purpose' => $listing->listing_purpose,
                'lat' => (float) $listing->latitude,
                'lng' => (float) $listing->longitude,
                'thumbnail' => $thumbnail,
            ];
        });

        return response()->json(['data' => $pins]);
    }

    // ── Products ──────────────────────────────────────────────────────────────

    private function browseProduct(Request $request, $country, string $id): JsonResponse
    {
        $country = $request->attributes->get('country');

        $category = Category::where('id', $id)->where('is_active', true)->firstOrFail();

        $catVersion = Cache::get("category_v:{$category->id}", 0);
        $categoryResource = Cache::remember(
            "browse_category_resource:{$category->id}:{$catVersion}",
            now()->addMinutes(30),
            fn () => (new ProductBrowseCategoryResource($category))->resolve()
        );

        $filters = $request->only([
            'price_min', 'price_max', 'brand', 'rating_min', 'condition',
            'fulfillment_model', 'include_oos', 'attributes', 'sort',
        ]);
        $perPage = $request->integer('per_page', 20);
        $page = $request->integer('page', 1);
        $categoryIds = $this->categories->getDescendantIds($category);

        $categoryNode = $this->unifiedCategories->findById($id, 'product');

        $pageBuilder = $this->pageBuilder->resolve(
            $country,
            'category',
            $category->id,
            $this->pageBuilder->detectDevice($request),
            auth('customer')->check() ? 'authenticated' : 'guest',
        );

        $attributeFilters = is_array($filters['attributes'] ?? null) ? $filters['attributes'] : [];

        $paginator = $this->products->paginate($country, $filters, $perPage, $categoryIds);
        $facets = $this->products->facets($country, $filters, $categoryIds);
        $payload = $this->products->buildProductsPayload(
            $paginator,
            $country,
            $page,
            'category_top',
            $categoryIds,
            $attributeFilters,
        );

        return response()->json([
            'success' => true,
            'data' => [
                'category' => $categoryResource,
                'category_node' => $categoryNode,
                'page_builder' => $pageBuilder,
                'listings' => array_merge($payload, ['facets' => $facets]),
            ],
        ]);
    }

    // ── Classifieds ───────────────────────────────────────────────────────────

    private function browseClassified(Request $request, $country, string $id): JsonResponse
    {
        $country = $request->attributes->get('country');

        $category = null;

        if ($id !== 'all' && $id !== '') {
            $categoryNode = $this->unifiedCategories->findById($id, 'classified');

            if (! $categoryNode) {
                return response()->json(['success' => false, 'message' => __('common.exceptions.browse.category_not_found')], 404);
            }

            $category = ClassifiedCategory::where('id', $categoryNode['id'])->firstOrFail();
        }

        $pageBuilder = $category
            ? $this->pageBuilder->resolve(
                $country,
                'category',
                $category->id,
                $this->pageBuilder->detectDevice($request),
                auth('customer')->check() ? 'authenticated' : 'guest',
            )
            : null;

        $perPage = $request->integer('per_page', 20);
        $filters = $request->only(['listing_purpose', 'seller_type', 'min_price', 'max_price']);

        $paginator = $this->listings->paginateForClassifiedCategory($category?->id, $perPage, $filters);

        $exclusiveContracts = $this->listings->exclusiveContractsForListings($paginator->getCollection());

        $items = $paginator->getCollection()
            ->map(fn ($listing) => $this->listings->toClassifiedCardShape($listing, $exclusiveContracts[$listing->id] ?? null))
            ->toArray();

        return response()->json([
            'success' => true,
            'data' => [
                'category' => $category ? new ClassifiedBrowseCategoryResource($category) : null,
                'page_builder' => $pageBuilder,
                'listings' => [
                    'items' => $items,
                    'meta' => [
                        'current_page' => $paginator->currentPage(),
                        'last_page' => $paginator->lastPage(),
                        'per_page' => $paginator->perPage(),
                        'total' => $paginator->total(),
                    ],
                ],
            ],
        ]);
    }

    // ── Travel ────────────────────────────────────────────────────────────────

    private function browseTravel(Request $request, $country, string $id): JsonResponse
    {
        $country = $request->attributes->get('country');

        $travelCategory = null;

        if ($id !== 'all' && $id !== '') {
            $travelCategory = TravelCategory::where('is_active', 1)
                ->where(fn ($query) => $query->where('id', $id)->orWhere('slug', $id))
                ->first();

            if (! $travelCategory) {
                return response()->json(['success' => false, 'message' => __('common.exceptions.browse.category_not_found')], 404);
            }
        }

        $pageBuilder = $travelCategory
            ? $this->pageBuilder->resolve(
                $country,
                'category',
                $travelCategory->id,
                $this->pageBuilder->detectDevice($request),
                auth('customer')->check() ? 'authenticated' : 'guest',
            )
            : null;

        // Documented names are departure_from/departure_to; date_from/date_to kept as aliases.
        $request->merge([
            'date_from' => $request->input('departure_from', $request->input('date_from')),
            'date_to' => $request->input('departure_to', $request->input('date_to')),
        ]);
        $request->validate([
            'country_id' => 'nullable|uuid|exists:travel_countries,id',
            'city_id' => ['nullable', 'uuid', Rule::exists('travel_cities', 'id')
                ->when($request->filled('country_id'), fn ($r) => $r->where('travel_country_id', $request->input('country_id')))],
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $perPage = $request->integer('per_page', 20);
        $paginator = $this->listings->paginateTravelPackages(
            $travelCategory?->id,
            $perPage,
            $request->string('country_id')->value() ?: null,
            $request->string('city_id')->value() ?: null,
            $request->string('date_from')->value() ?: null,
            $request->string('date_to')->value() ?: null,
        );

        $items = $paginator->getCollection()
            ->map(fn ($package) => $this->listings->toTravelCardShape($package))
            ->toArray();

        $availableCategories = TravelCategorySummaryResource::collection(
            TravelCategory::where('is_active', 1)
                ->orderBy('sort_order')
                ->withCount('packages')
                ->get()
        );

        return response()->json([
            'success' => true,
            'data' => [
                'category' => new TravelBrowseCategoryResource($travelCategory),
                'available_categories' => $availableCategories,
                'page_builder' => $pageBuilder,
                'listings' => [
                    'items' => $items,
                    'meta' => [
                        'current_page' => $paginator->currentPage(),
                        'last_page' => $paginator->lastPage(),
                        'per_page' => $paginator->perPage(),
                        'total' => $paginator->total(),
                    ],
                ],
            ],
        ]);
    }
}
