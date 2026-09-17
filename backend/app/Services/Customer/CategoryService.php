<?php

namespace App\Services\Customer;

use App\Http\Resources\Customer\CategoryTreeResource;
use App\Http\Resources\Customer\ClassifiedCategoryTreeResource;
use App\Http\Resources\Customer\TravelCategoryTreeResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\ClassifiedCategory;
use App\Models\Country;
use App\Models\CustomPage;
use App\Models\File;
use App\Models\Page;
use App\Models\Slug;
use App\Models\TravelCategory;
use App\Services\PageBuilderService;
use App\Support\Bilingual;
use App\Support\SafeCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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

        return SafeCache::remember("category_tree_v{$version}_{$country->id}", 600, function () {
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
     * enhancement.md P-21 task 1/2/3/4/6: lightweight nav tree for the
     * storefront header (frontend/src/layout/noon/header). Merges product +
     * classified + travel categories the same way getTree() does, but for
     * the product side it:
     *   - loads every active/visible category for the country in ONE query
     *     (joined against country_categories) and builds the tree in PHP
     *     from parent_id, instead of one nested-set query per node;
     *   - resolves images with one bulk `files` query keyed by category id;
     *   - resolves per-category brand + product counts from ONE grouped
     *     query over the P-19 `product_country_buybox` read model, rolled
     *     up to ancestors in PHP — no per-category `brands where exists (...)`
     *     query and no `category_attributes` pivot load (the header does not
     *     use attributes at all).
     * Cached per (country, locale) under the shared 'categories' tag.
     */
    public function getNavTree(Country $country): array
    {
        $locale = app()->getLocale();
        $version = Cache::get(self::CACHE_VERSION_KEY, 1);

        return SafeCache::tags(['categories'])->remember(
            "category_nav_v{$version}_{$country->id}_{$locale}",
            600,
            function () use ($country) {
                $productNodes = $this->buildProductTree($country, withAttributes: false, brandsLimit: 5);

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
                    $productNodes,
                    ClassifiedCategoryTreeResource::collection($classifiedNodes)->resolve(),
                    TravelCategoryTreeResource::collection($travelNodes)->resolve(),
                );
            }
        );
    }

    /**
     * enhancement.md P-21 task 6: the heavy browse payload — full product
     * category detail (brand list + counts + filterable attributes) for
     * category browse pages. Same bulk-query strategy as getNavTree(), plus
     * one extra bulk `category_attributes` + `attribute_values` pair of
     * queries. Deliberately a *separate* cache entry from the nav tree so
     * the cheap header call never pays for attributes.
     */
    public function getBrowseTree(Country $country): array
    {
        $locale = app()->getLocale();
        $version = Cache::get(self::CACHE_VERSION_KEY, 1);

        return SafeCache::tags(['categories'])->remember(
            "category_browse_v{$version}_{$country->id}_{$locale}",
            600,
            fn () => $this->buildProductTree($country, withAttributes: true, brandsLimit: null)
        );
    }

    /**
     * Shared builder for both trees: one query for the categories
     * themselves (joined against country_categories), one for images, one
     * grouped query for brand/product counts over product_country_buybox,
     * one for brand names, and — only when $withAttributes — two more for
     * filterable attributes + values. Descendant roll-up of counts happens
     * in PHP via a single post-order pass over the parent_id tree.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildProductTree(Country $country, bool $withAttributes, ?int $brandsLimit): array
    {
        // Query 1: every active/visible category available in this country.
        // A missing country_categories row means "available everywhere" by
        // default; an explicit row only excludes it when is_available = 0.
        $categories = Category::query()
            ->select('categories.*')
            ->leftJoin('country_categories', function ($join) use ($country) {
                $join->on('country_categories.category_id', '=', 'categories.id')
                    ->where('country_categories.country_id', $country->id);
            })
            ->whereNull('categories.deleted_at')
            ->where('categories.is_active', true)
            ->where('categories.is_visible', true)
            ->where(function ($q) {
                $q->whereNull('country_categories.id')->orWhere('country_categories.is_available', true);
            })
            ->orderBy('categories.sort_order')
            ->get();

        if ($categories->isEmpty()) {
            return [];
        }

        $ids = $categories->pluck('id')->all();

        // Query 2: bulk image lookup, one row per category (primary file first).
        $imagesById = File::query()
            ->where('model_type', Category::class)
            ->whereIn('model_id', $ids)
            ->orderByDesc('is_primary')
            ->orderBy('position')
            ->get()
            ->groupBy('model_id')
            ->map(fn ($files) => $files->first()->full_path);

        // Query 3: grouped (category_id, brand_id) product counts, this
        // country only, straight off the P-19 buy-box read model — no
        // per-category `brands where exists (...)` query.
        $buyboxRows = DB::table('product_country_buybox')
            ->where('country_id', $country->id)
            ->whereIn('category_id', $ids)
            ->select('category_id', 'brand_id', DB::raw('COUNT(*) as product_count'))
            ->groupBy('category_id', 'brand_id')
            ->get();

        $brandIds = $buyboxRows->pluck('brand_id')->filter()->unique()->values()->all();

        // Query 4: brand names/logos for the brand ids actually referenced.
        $brandsById = empty($brandIds)
            ? collect()
            : Brand::whereIn('id', $brandIds)->where('is_active', true)->get()->keyBy('id');

        // Own-category counts, before descendant roll-up.
        $ownCounts = []; // categoryId => ['product_count' => int, 'brands' => [brandId => count]]
        foreach ($buyboxRows as $row) {
            $ownCounts[$row->category_id]['product_count'] = ($ownCounts[$row->category_id]['product_count'] ?? 0) + $row->product_count;
            if ($row->brand_id) {
                $ownCounts[$row->category_id]['brands'][$row->brand_id] = ($ownCounts[$row->category_id]['brands'][$row->brand_id] ?? 0) + $row->product_count;
            }
        }

        $byParent = $categories->groupBy('parent_id');

        $attributesByCategory = $withAttributes ? $this->loadAttributesForCategories($ids) : [];

        // Post-order roll-up: for every category, aggregate its own counts
        // with everything already computed for its children.
        $rolledUp = []; // categoryId => ['product_count' => int, 'brands' => [brandId => count]]

        $rollUp = function (Category $category) use (&$rollUp, $byParent, $ownCounts, &$rolledUp) {
            $agg = $ownCounts[$category->id] ?? ['product_count' => 0, 'brands' => []];

            foreach ($byParent->get($category->id, collect()) as $child) {
                $childAgg = $rollUp($child);
                $agg['product_count'] += $childAgg['product_count'];
                foreach ($childAgg['brands'] as $brandId => $count) {
                    $agg['brands'][$brandId] = ($agg['brands'][$brandId] ?? 0) + $count;
                }
            }

            $rolledUp[$category->id] = $agg;

            return $agg;
        };

        foreach ($byParent->get(null, collect()) as $root) {
            $rollUp($root);
        }

        $build = function (?string $parentId) use (
            &$build, $byParent, $imagesById, $rolledUp, $brandsById, $brandsLimit, $attributesByCategory, $withAttributes
        ) {
            return $byParent->get($parentId, collect())
                ->map(function (Category $category) use (&$build, $imagesById, $rolledUp, $brandsById, $brandsLimit, $attributesByCategory, $withAttributes) {
                    $agg = $rolledUp[$category->id] ?? ['product_count' => 0, 'brands' => []];

                    $brandIds = array_keys($agg['brands']);
                    if ($brandsLimit !== null) {
                        // Highest product_count first, capped.
                        arsort($agg['brands']);
                        $brandIds = array_slice(array_keys($agg['brands']), 0, $brandsLimit);
                    }

                    $node = [
                        'id'            => $category->id,
                        'type'          => 'product',
                        'name'          => Bilingual::pair($category, 'name'),
                        'slug'          => $category->slug,
                        'parent_id'     => $category->parent_id,
                        'image_url'     => $imagesById[$category->id] ?? null,
                        'product_count' => (int) $agg['product_count'],
                        'brands'        => collect($brandIds)
                            ->filter(fn ($id) => $brandsById->has($id))
                            ->map(fn ($id) => [
                                'id'       => $brandsById[$id]->id,
                                'name'     => Bilingual::pair($brandsById[$id], 'name'),
                                'slug'     => $brandsById[$id]->slug,
                                'logo_url' => $brandsById[$id]->logo_url,
                            ])->values()->all(),
                        'children'      => $build($category->id),
                    ];

                    if ($withAttributes) {
                        $node['attributes'] = $attributesByCategory[$category->id] ?? [];
                    }

                    return $node;
                })
                ->values()
                ->all();
        };

        return $build(null);
    }

    /**
     * Bulk-load filterable attributes + values for every category id, two
     * queries total instead of one `->attributes()->with('values')->get()`
     * call per category node.
     *
     * @param list<string> $categoryIds
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function loadAttributesForCategories(array $categoryIds): array
    {
        $pivots = DB::table('category_attributes')
            ->join('attributes', 'attributes.id', '=', 'category_attributes.attribute_id')
            ->whereIn('category_attributes.category_id', $categoryIds)
            ->where('attributes.is_filterable', true)
            ->orderBy('category_attributes.sort_order')
            ->select(
                'category_attributes.category_id',
                'category_attributes.is_required',
                'attributes.id as attribute_id',
                'attributes.code',
                'attributes.name_ar',
                'attributes.name_en',
                'attributes.type',
                'attributes.unit'
            )
            ->get();

        if ($pivots->isEmpty()) {
            return [];
        }

        $attributeIds = $pivots->pluck('attribute_id')->unique()->values()->all();

        $valuesByAttribute = DB::table('attribute_values')
            ->whereIn('attribute_id', $attributeIds)
            ->orderBy('sort_order')
            ->get()
            ->groupBy('attribute_id');

        $byCategory = [];
        foreach ($pivots as $pivot) {
            $byCategory[$pivot->category_id][] = [
                'id'          => $pivot->attribute_id,
                'code'        => $pivot->code,
                'name'        => Bilingual::pairFromKeys($pivot, 'name_ar', 'name_en'),
                'type'        => is_string($pivot->type) ? $pivot->type : $pivot->type->value,
                'unit'        => $pivot->unit,
                'is_required' => (bool) $pivot->is_required,
                'values'      => ($valuesByAttribute[$pivot->attribute_id] ?? collect())
                    ->map(fn ($value) => [
                        'id'        => $value->id,
                        'value'     => Bilingual::pairFromKeys($value, 'value_ar', 'value_en'),
                        'color_hex' => $value->code_hex,
                        'swatch_image_url' => $value->swatch_image_path
                            ? (new \App\Models\AttributeValue(['swatch_image_path' => $value->swatch_image_path]))->swatch_image_url
                            : null,
                    ])->values()->all(),
            ];
        }

        return $byCategory;
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
     * Resolve a storefront slug (or raw category id, for back-compat with
     * existing id-based callers) to the entity it points at — a Category
     * page or a CustomPage (an aggregate landing page spanning several
     * categories, e.g. a noon-deals-style page). Looks up the polymorphic
     * `slugs` table first, then falls back to a direct category id lookup.
     *
     * @return array{type: 'category'|'custom_page', model: Category|CustomPage}|null
     */
    public function resolveSlug(string $idOrSlug): ?array
    {
        $slug = Slug::where('slug_url', $idOrSlug)->first();

        if ($slug) {
            $model = $slug->sluggable;

            return match (true) {
                $model instanceof Category   => ['type' => 'category', 'model' => $model],
                $model instanceof CustomPage => ['type' => 'custom_page', 'model' => $model],
                default => null,
            };
        }

        $category = Category::where('id', $idOrSlug)->first();

        return $category ? ['type' => 'category', 'model' => $category] : null;
    }

    /**
     * The effective set of category IDs a product-listing filter value
     * (category slug, custom-page slug, or raw category id) should scope
     * results to — a category's own subtree, or the union of subtrees for
     * every category linked to a custom page.
     *
     * @return list<string>
     */
    public function getCategoryIdsForFilter(string $idOrSlug): array
    {
        $resolved = $this->resolveSlug($idOrSlug);

        if (!$resolved) {
            return [$idOrSlug];
        }

        if ($resolved['type'] === 'category') {
            return $this->getDescendantIds($resolved['model']);
        }

        $ids = [];
        foreach ($resolved['model']->categories as $category) {
            $ids = array_merge($ids, $this->getDescendantIds($category));
        }

        return array_values(array_unique($ids));
    }

    /**
     * Cached product count for a category + its descendants.
     * Uses the product_count column maintained by RecalculateCategoryStatsJob.
     */
    public function productCount(Category $category, Country $country): int
    {
        return SafeCache::tags(['categories'])
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
            $cached = SafeCache::tags(['pages'])
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
