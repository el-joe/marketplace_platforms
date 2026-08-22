<?php

namespace App\Services\Customer;

use App\Http\Resources\Customer\BrowseCategoryResource;
use App\Http\Resources\Customer\ClassifiedListingPublicResource;
use App\Http\Resources\Customer\TravelPackagePublicResource;
use App\Models\Category;
use App\Models\ClassifiedCategory;
use App\Models\Country;
use App\Models\Page;
use App\Models\TravelCategory;
use App\Services\PageBuilderService;
use App\Support\SafeCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class BrowseService
{
    public function __construct(
        private readonly CategoryService $categoryService,
        private readonly ProductQueryService $productQuery,
        private readonly ClassifiedBrowseQueryService $classifiedQuery,
        private readonly TravelBrowseQueryService $travelQuery,
        private readonly PageBuilderService $pageBuilder,
    ) {}

    public function browse(
        string $type,
        string $slug,
        Country $country,
        Request $request,
    ): array {
        return match ($type) {
            'product'    => $this->browseProducts($slug, $country, $request),
            'classified' => $this->browseClassifieds($slug, $country, $request),
            'travel'     => $this->browseTravel($slug, $country, $request),
            default      => throw new NotFoundHttpException(__('common.exceptions.browse.unknown_type', ['type' => $type])),
        };
    }

    // ── Products ──────────────────────────────────────────────────────────────

    private function browseProducts(string $slug, Country $country, Request $request): array
    {
        $category = Category::where('slug', $slug)->where('is_active', true)->firstOrFail();

        $filters     = $request->only([
            'price_min', 'price_max', 'brand', 'rating_min', 'condition',
            'fulfillment_model', 'include_oos', 'attributes', 'sort',
        ]);
        $perPage     = (int) ($request->input('per_page', 20));
        $page        = (int) ($request->input('page', 1));
        $categoryIds = $this->categoryService->getDescendantIds($category);

        $paginator    = $this->productQuery->paginate($country, $filters, $perPage, $categoryIds);
        $facets       = $this->productQuery->facets($country, $filters, $categoryIds);
        $payload      = $this->productQuery->buildProductsPayload($paginator, $country, $page, 'category_top');
        $pageBuilder  = $this->categoryService->resolvePageBuilder($category, $country);

        return [
            'category'     => (new BrowseCategoryResource($category, 'product'))->toArray($request),
            'page_builder' => $pageBuilder,
            'items'        => array_merge($payload, ['facets' => $facets]),
        ];
    }

    // ── Classifieds ───────────────────────────────────────────────────────────

    private function browseClassifieds(string $slug, Country $country, Request $request): array
    {
        $category = ClassifiedCategory::where('slug', $slug)->where('is_active', true)->firstOrFail();

        $filters     = $request->only(['price_min', 'price_max', 'listing_purpose', 'city_id', 'sort']);
        $perPage     = (int) ($request->input('per_page', 20));
        $categoryIds = $this->classifiedQuery->getDescendantIds($category);

        $paginator    = $this->classifiedQuery->paginate($categoryIds, $filters, $perPage);
        $facets       = $this->classifiedQuery->facets($categoryIds, $filters);
        $pageBuilder  = $this->resolveGenericPageBuilder('classified_category', $category, $country);

        $items = ClassifiedListingPublicResource::collection($paginator)->toArray($request);

        return [
            'category'     => (new BrowseCategoryResource($category, 'classified'))->toArray($request),
            'page_builder' => $pageBuilder,
            'items'        => [
                'items'  => $items,
                'meta'   => [
                    'current_page' => $paginator->currentPage(),
                    'last_page'    => $paginator->lastPage(),
                    'per_page'     => $paginator->perPage(),
                    'total'        => $paginator->total(),
                ],
                'facets' => $facets,
            ],
        ];
    }

    // ── Travel ────────────────────────────────────────────────────────────────

    private function browseTravel(string $slug, Country $country, Request $request): array
    {
        $category = TravelCategory::where('slug', $slug)->where('is_active', true)->firstOrFail();

        $filters     = $request->only([
            'price_min', 'price_max', 'duration_min', 'duration_max',
            'destination_country', 'departure_from', 'departure_to', 'sort',
        ]);
        $perPage     = (int) ($request->input('per_page', 20));
        $categoryIds = $this->travelQuery->getDescendantIds($category);

        $paginator    = $this->travelQuery->paginate($categoryIds, $filters, $perPage);
        $facets       = $this->travelQuery->facets($categoryIds, $filters);
        $pageBuilder  = $this->resolveGenericPageBuilder('travel_category', $category, $country);

        $items = TravelPackagePublicResource::collection($paginator)->toArray($request);

        return [
            'category'     => (new BrowseCategoryResource($category, 'travel'))->toArray($request),
            'page_builder' => $pageBuilder,
            'items'        => [
                'items'  => $items,
                'meta'   => [
                    'current_page' => $paginator->currentPage(),
                    'last_page'    => $paginator->lastPage(),
                    'per_page'     => $paginator->perPage(),
                    'total'        => $paginator->total(),
                ],
                'facets' => $facets,
            ],
        ];
    }

    // ── Page blocks (adjacency-list models) ───────────────────────────────────

    /**
     * Walk the parent_id chain for classified/travel categories and find the
     * nearest ancestor with a published page of the given page_type.
     *
     * @return array{blocks: list<array<string,mixed>>, sections: list<array<string,mixed>>, has_sections: bool}
     */
    private function resolveGenericPageBuilder(string $pageType, mixed $category, Country $country): array
    {
        $chain = $this->buildAncestorChain($category);

        foreach ($chain as $node) {
            $cacheKey = "browse_page_blocks:{$pageType}:{$country->id}:{$node->id}";

            $cached = SafeCache::tags(['pages'])->remember($cacheKey, 300, function () use ($pageType, $node, $country) {
                $page = Page::where('page_type', $pageType)
                    ->where('reference_id', $node->id)
                    ->where('country_id', $country->id)
                    ->where('status', 'published')
                    ->where('is_default', true)
                    ->first();

                if (!$page) {
                    return ['found' => false, 'blocks' => [], 'sections' => []];
                }

                $pageData = $this->pageBuilder->getPageWithBlocks($page->id);

                return [
                    'found'    => true,
                    'blocks'   => $pageData['blocks'],
                    'sections' => $pageData['sections'],
                ];
            });

            if ($cached['found']) {
                return [
                    'blocks'       => $cached['blocks'],
                    'sections'     => $cached['sections'],
                    'has_sections' => count($cached['sections']) > 0,
                ];
            }
        }

        return ['blocks' => [], 'sections' => [], 'has_sections' => false];
    }

    /** Returns [$category, $parent, $grandparent, ...] via parent_id chain. */
    private function buildAncestorChain(mixed $category): array
    {
        $chain = [$category];
        $node  = $category;

        while ($node->parent_id) {
            $node = $node->parent()->first();
            if (!$node) {
                break;
            }
            $chain[] = $node;
        }

        return $chain;
    }
}
