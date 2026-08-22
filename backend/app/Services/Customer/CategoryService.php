<?php

namespace App\Services\Customer;

use App\Http\Resources\Customer\CategoryTreeResource;
use App\Http\Resources\Customer\ClassifiedCategoryTreeResource;
use App\Http\Resources\Customer\TravelCategoryTreeResource;
use App\Models\Category;
use App\Models\ClassifiedCategory;
use App\Models\Country;
use App\Models\Page;
use App\Models\TravelCategory;
use App\Services\PageBuilderService;
use Illuminate\Support\Facades\Cache;

class CategoryService
{
    public const CACHE_VERSION_KEY = 'category_tree_cache_version';

    public function __construct(
        private readonly PageBuilderService $pageBuilder,
    ) {}

    /**
     * Full nested category tree for nav/menu use, merging product categories
     * with classified and travel categories into a single array. Every node
     * carries a 'type' of 'product', 'classified', or 'travel'.
     * Cached 10 min per country, versioned so Category/ClassifiedCategory/
     * TravelCategory saves invalidate it.
     */
    public function getTree(Country $country): array
    {
        $version = Cache::get(self::CACHE_VERSION_KEY, 1);

        return Cache::remember("category_tree_v{$version}_{$country->id}", 600, function () {
                // toTree() builds the hierarchy in PHP from a single lft/rgt-ordered query.
                $productNodes = Category::where('is_active', true)
                    ->where('is_visible', true)
                    ->orderBy('sort_order')
                    ->get()
                    ->toTree();

                $classifiedNodes = ClassifiedCategory::whereNull('parent_id')
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->with(['children' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
                    ->get();

                $travelNodes = TravelCategory::whereNull('parent_id')
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->with(['children' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
                    ->get();

                return array_merge(
                    CategoryTreeResource::collection($productNodes)->resolve(),
                    ClassifiedCategoryTreeResource::collection($classifiedNodes)->resolve(),
                    TravelCategoryTreeResource::collection($travelNodes)->resolve(),
                );
            });
    }

    public static function flushCache(): void
    {
        $version = Cache::get(self::CACHE_VERSION_KEY, 1);
        Cache::put(self::CACHE_VERSION_KEY, $version + 1);
    }

    /**
     * IDs of $category plus all active descendants — single query via nested-set lft/rgt range.
     *
     * @return list<string>
     */
    public function getDescendantIds(Category $category): array
    {
        return $category->descendants()
            ->where('is_active', true)
            ->pluck('id')
            ->prepend($category->id)
            ->toArray();
    }

    /**
     * Cached product count for a category + its descendants.
     * Uses the product_count column maintained by RecalculateCategoryStatsJob.
     */
    public function productCount(Category $category, Country $country): int
    {
        return Cache::tags(['categories'])
            ->remember("category_product_count:{$country->id}:{$category->id}", 600, function () use ($category) {
                return (int) $category->product_count;
            });
    }

    /**
     * Resolve the page builder data (blocks + sections) for a category page,
     * walking up the ancestor chain until a published default page is found
     * for this country.
     * Cached 5 min per (category, country), flushed by PageBuilderService::flushPageCache
     * via the shared 'pages' cache tag.
     *
     * @return array{blocks: list<array<string,mixed>>, sections: list<array<string,mixed>>, has_sections: bool}
     */
    public function resolvePageBuilder(Category $category, Country $country): array
    {
        // Build ancestor chain: [this category, parent, grandparent, ...]
        $chain = collect([$category])
            ->merge(
                $category->ancestors()->orderByDesc('depth')->get()
            );

        foreach ($chain as $node) {
            $cacheKey = "category_page_blocks:{$country->id}:{$node->id}";

            // Wrap result in an array so Cache::remember can safely store a "not found" state
            // without ambiguity around null values.
            $cached = Cache::tags(['pages'])
                ->remember($cacheKey, 300, function () use ($node, $country) {
                    $page = Page::where('page_type', 'category')
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
}
