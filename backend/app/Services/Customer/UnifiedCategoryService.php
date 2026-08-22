<?php

namespace App\Services\Customer;

use App\Models\Category;
use App\Models\ClassifiedCategory;
use App\Models\Country;
use App\Models\TravelCategory;
use App\Support\Bilingual;
use Illuminate\Support\Facades\Cache;

class UnifiedCategoryService
{
    public const CACHE_VERSION_KEY = 'unified_nav_cache_version';

    /**
     * Full merged tree for the nav API, sectioned by source and cached per
     * country. Versioned so Category/ClassifiedCategory/TravelCategory saves
     * invalidate it.
     */
    public function getMergedTree(Country $country): array
    {
        $version = Cache::get(self::CACHE_VERSION_KEY, 1);

        // Cache key is per-country, but productTree()/classifiedTree()/travelTree() query
        // globally with no country filter — category trees are shared across all countries
        // by design, so $country is only used to namespace the cache entry.
        return Cache::remember("unified_nav_v{$version}_{$country->id}", 600, function () {
            return [
                ['section' => 'products', 'nodes' => $this->productTree()],
                ['section' => 'classifieds', 'nodes' => $this->classifiedTree()],
                ['section' => 'travel', 'nodes' => $this->travelTree()],
            ];
        });
    }

    public static function flushCache(): void
    {
        $version = Cache::get(self::CACHE_VERSION_KEY, 1);
        Cache::put(self::CACHE_VERSION_KEY, $version + 1);
    }

    /**
     * Flat merged list of all categories from all sources, parents and children alike.
     */
    public function getFlatList(Country $country): array
    {
        $products = Category::whereNull('parent_id')
            ->where('is_active', 1)
            ->where('is_visible', 1)
            ->orderBy('sort_order')
            ->orderBy('lft')
            ->with(['children' => fn ($q) => $q->where('is_active', 1)
                ->where('is_visible', 1)->orderBy('sort_order')])
            ->get();

        $classifieds = ClassifiedCategory::whereNull('parent_id')
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->with(['children' => fn ($q) => $q->where('is_active', 1)->orderBy('sort_order')])
            ->get();

        $travel = TravelCategory::whereNull('parent_id')
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->with(['children' => fn ($q) => $q->where('is_active', 1)->orderBy('sort_order')])
            ->get();

        $flat = [];

        foreach ($products as $category) {
            $flat[] = $this->flatNode($category, 'product', 0, null);
            foreach ($category->children as $child) {
                $flat[] = $this->flatNode($child, 'product', 1, $category->id);
            }
        }

        foreach ($classifieds as $category) {
            $flat[] = $this->flatNode($category, 'classified', 0, null);
            foreach ($category->children as $child) {
                $flat[] = $this->flatNode($child, 'classified', 1, $category->id);
            }
        }

        foreach ($travel as $category) {
            $flat[] = $this->flatNode($category, 'travel', 0, null);
            foreach ($category->children as $child) {
                $flat[] = $this->flatNode($child, 'travel', 1, $category->id);
            }
        }

        return $flat;
    }

    /**
     * Single unified node by id + source_type, without children.
     */
    public function findById(string $id, string $sourceType): ?array
    {
        $category = match ($sourceType) {
            'product' => Category::where('id', $id)
                ->where('is_active', 1)
                ->where('is_visible', 1)
                ->first(),
            'classified' => ClassifiedCategory::where('id', $id)
                ->where('is_active', 1)
                ->first(),
            'travel' => TravelCategory::where('id', $id)
                ->where('is_active', 1)
                ->first(),
            default => null,
        };

        if (!$category) {
            return null;
        }

        return $this->mapNode($category, $sourceType, 0, []);
    }

    /**
     * Search all three category sources by name, merged and capped for suggestions.
     */
    public function search(string $query): array
    {
        $products = Category::where(fn ($q) => $q->where('name_en', 'like', "%{$query}%")
                ->orWhere('name_ar', 'like', "%{$query}%"))
            ->where('is_active', 1)
            ->where('is_visible', 1)
            ->limit(3)
            ->get()
            ->map(fn ($category) => $this->mapNode($category, 'product', 0, []));

        $classifieds = ClassifiedCategory::where(fn ($q) => $q->where('name_en', 'like', "%{$query}%")
                ->orWhere('name_ar', 'like', "%{$query}%"))
            ->where('is_active', 1)
            ->limit(2)
            ->get()
            ->map(fn ($category) => $this->mapNode($category, 'classified', 0, []));

        $travel = TravelCategory::where(fn ($q) => $q->where('name_en', 'like', "%{$query}%")
                ->orWhere('name_ar', 'like', "%{$query}%"))
            ->where('is_active', 1)
            ->limit(2)
            ->get()
            ->map(fn ($category) => $this->mapNode($category, 'travel', 0, []));

        return $products->concat($classifieds)->concat($travel)
            ->map(function ($node) {
                unset($node['children'], $node['is_featured'], $node['depth'], $node['sort_order']);

                return $node;
            })
            ->take(7)
            ->values()
            ->all();
    }

    private function productTree(): array
    {
        $roots = Category::whereNull('parent_id')
            ->where('is_active', 1)
            ->where('is_visible', 1)
            ->orderBy('sort_order')
            ->orderBy('lft')
            ->with(['children' => fn ($q) => $q->where('is_active', 1)
                ->where('is_visible', 1)->orderBy('sort_order')])
            ->get();

        return $roots->map(function ($category) {
            $children = $category->children->map(
                fn ($child) => $this->mapNode($child, 'product', 1, [])
            )->values()->all();

            return $this->mapNode($category, 'product', 0, $children);
        })->values()->all();
    }

    private function classifiedTree(): array
    {
        $roots = ClassifiedCategory::whereNull('parent_id')
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->with(['children' => fn ($q) => $q->where('is_active', 1)->orderBy('sort_order')])
            ->get();

        return $roots->map(function ($category) {
            $children = $category->children->map(
                fn ($child) => $this->mapNode($child, 'classified', 1, [])
            )->values()->all();

            return $this->mapNode($category, 'classified', 0, $children);
        })->values()->all();
    }

    private function travelTree(): array
    {
        $roots = TravelCategory::whereNull('parent_id')
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->with(['children' => fn ($q) => $q->where('is_active', 1)->orderBy('sort_order')])
            ->get();

        return $roots->map(function ($category) {
            $children = $category->children->map(
                fn ($child) => $this->mapNode($child, 'travel', 1, [])
            )->values()->all();

            return $this->mapNode($category, 'travel', 0, $children);
        })->values()->all();
    }

    private function mapNode(mixed $category, string $sourceType, int $depth, array $children): array
    {
        $node = [
            'id'          => $category->id,
            'source_type' => $sourceType,
            'name'        => Bilingual::pair($category, 'name'),
            'slug'        => $category->slug,
            'icon'        => $sourceType === 'product' ? null : $category->icon,
            'is_featured' => $sourceType === 'product' ? (bool) $category->is_featured : false,
            'depth'       => $depth,
            'sort_order'  => (int) $category->sort_order,
            'link'        => $this->linkFor($category, $sourceType),
            'children'    => $children,
        ];

        if ($sourceType === 'travel') {
            $node['travel_category_slug'] = $category->slug;
        }

        return $node;
    }

    private function flatNode(mixed $category, string $sourceType, int $depth, ?string $parentId): array
    {
        $node = $this->mapNode($category, $sourceType, $depth, []);
        unset($node['children']);
        $node['parent_id'] = $parentId;

        return $node;
    }

    private function linkFor(mixed $category, string $sourceType): string
    {
        return match ($sourceType) {
            'product' => "/browse/product/{$category->id}",
            'classified' => "/browse/classified/{$category->id}",
            'travel' => '/travel',
        };
    }
}
