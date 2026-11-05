<?php

namespace App\Services\Customer;

use App\Enums\ClassifiedListingStatus;
use App\Enums\ClassifiedPlacement;
use App\Models\ClassifiedCategory;
use App\Models\ClassifiedListing;
use App\Models\Country;
use App\Models\File;
use App\Support\SafeCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * What the storefront may show of the classified category tree, and where.
 *
 * A category is VISIBLE for a visitor's country when it is active, its country scope is empty (every country) or
 * contains that country, and every ancestor is visible too. Everything public (navigation, landing pages, browse,
 * search, map pins, ad detail pages, home sections) is derived from this one rule, so disabling a category or a
 * parent hides the whole branch consistently while leaving every record untouched.
 *
 * Sections are the visible top-level categories; the admin chooses the surfaces they appear on (placements),
 * their order (sort_order) and their countries. Nothing here knows a category by name.
 */
class ClassifiedSectionService
{
    /**
     * Every category with its visibility flags and display columns, from ONE query, cached per
     * category-cache version (bumped by ClassifiedCategory saves and country-scope changes).
     *
     * @return array<string, array<string, mixed>> keyed by id; `countries` is the list of country ids (empty = all)
     */
    private function nodes(): array
    {
        $version = Cache::get(UnifiedCategoryService::CACHE_VERSION_KEY, 1);

        return SafeCache::remember("classified_visibility_v{$version}", 600, function (): array {
            $rows = DB::table('classified_categories as c')
                ->leftJoin('classified_category_country as cc', 'cc.classified_category_id', '=', 'c.id')
                ->get([
                    'c.id', 'c.parent_id', 'c.is_active', 'c.name_en', 'c.name_ar', 'c.slug', 'c.icon', 'c.placements',
                    'c.description_en', 'c.description_ar', 'c.sort_order', 'cc.country_id',
                ]);

            return $rows->groupBy('id')->map(function (Collection $group): array {
                $first = $group->first();

                return [
                    'id' => $first->id,
                    'parent_id' => $first->parent_id,
                    'is_active' => (bool) $first->is_active,
                    'countries' => $group->pluck('country_id')->filter()->values()->all(),
                    'display' => [
                        'id' => $first->id, 'parent_id' => $first->parent_id, 'is_active' => $first->is_active,
                        'name_en' => $first->name_en, 'name_ar' => $first->name_ar, 'slug' => $first->slug, 'icon' => $first->icon,
                        'placements' => $first->placements, 'description_en' => $first->description_en,
                        'description_ar' => $first->description_ar, 'sort_order' => $first->sort_order,
                    ],
                ];
            })->all();
        });
    }

    /**
     * @return list<string> ids of the categories a visitor of this country may see (all countries when null)
     */
    public function visibleIds(?Country $country = null): array
    {
        $nodes = $this->nodes();
        $memo = [];
        $visible = [];

        foreach (array_keys($nodes) as $id) {
            if ($this->resolveVisible($id, $nodes, $country?->id, $memo)) {
                $visible[] = $id;
            }
        }

        return $visible;
    }

    public function isVisible(string $categoryId, ?Country $country = null): bool
    {
        $nodes = $this->nodes();
        $memo = [];

        return isset($nodes[$categoryId]) && $this->resolveVisible($categoryId, $nodes, $country?->id, $memo);
    }

    /**
     * @param  array<string, array<string, mixed>>  $nodes
     * @param  array<string, bool>  $memo
     */
    private function resolveVisible(string $id, array $nodes, ?string $countryId, array &$memo, int $depth = 0): bool
    {
        if (isset($memo[$id])) {
            return $memo[$id];
        }

        $node = $nodes[$id] ?? null;

        if ($node === null || $depth > 20) {
            return $memo[$id] = false;
        }

        $ok = $node['is_active']
            && ($countryId === null || $node['countries'] === [] || in_array($countryId, $node['countries'], true))
            && ($node['parent_id'] === null || $this->resolveVisible($node['parent_id'], $nodes, $countryId, $memo, $depth + 1));

        return $memo[$id] = $ok;
    }

    /**
     * Visible category by id or slug (the storefront URL uses the slug; ids keep working for old links).
     */
    public function find(string $idOrSlug, ?Country $country = null): ?ClassifiedCategory
    {
        $category = ClassifiedCategory::query()
            ->when(
                Str::isUuid($idOrSlug),
                fn (Builder $query) => $query->where('id', $idOrSlug),
                fn (Builder $query) => $query->where('slug', $idOrSlug),
            )
            ->first();

        return $category && $this->isVisible($category->id, $country) ? $category : null;
    }

    /**
     * The category and its visible descendants (the ads of a section include its sub-categories).
     *
     * @return list<string>
     */
    public function visibleBranchIds(ClassifiedCategory $category, ?Country $country = null): array
    {
        $visible = array_flip($this->visibleIds($country));

        if (! isset($visible[$category->id])) {
            return [];
        }

        $nodes = $this->nodes();
        $ids = [$category->id];

        for ($i = 0; $i < count($ids); $i++) {
            foreach ($nodes as $id => $node) {
                if ($node['parent_id'] === $ids[$i] && isset($visible[$id]) && ! in_array($id, $ids, true)) {
                    $ids[] = $id;
                }
            }
        }

        return $ids;
    }

    /**
     * Visible sections (top-level categories) with their visible sub-categories, in the admin's order.
     * With a placement only the sections configured for that surface are returned.
     *
     * @return Collection<int, ClassifiedCategory> roots, each with its visible `children` relation loaded
     */
    public function tree(?Country $country = null, ?ClassifiedPlacement $placement = null): Collection
    {
        $nodes = $this->nodes();
        $visible = $this->visibleIds($country);

        if ($visible === []) {
            return collect();
        }

        $categories = collect($visible)
            ->map(fn (string $id) => (new ClassifiedCategory)->newFromBuilder($nodes[$id]['display']))
            ->sortBy([['sort_order', 'asc'], ['name_en', 'asc']])
            ->values();

        $images = File::query()
            ->where('model_type', ClassifiedCategory::class)
            ->whereIn('model_id', $visible)
            ->where('is_primary', true)
            ->orderBy('position')
            ->get()
            ->groupBy('model_id');

        $categories->each(fn (ClassifiedCategory $category) => $category->setRelation('primaryImage', $images->get($category->id)?->first()));

        $byParent = $categories->groupBy('parent_id');

        return $categories->whereNull('parent_id')
            ->filter(fn (ClassifiedCategory $root) => $placement === null || $root->appearsOn($placement))
            ->each(fn (ClassifiedCategory $root) => $root->setRelation('children', $byParent->get($root->id, collect())->values()))
            ->values();
    }

    /**
     * Public ads: active ads of visible categories (and, when a country is given, of that country).
     *
     * @return Builder<ClassifiedListing>
     */
    public function publicListings(?Country $country = null, ?string $categoryId = null): Builder
    {
        if ($categoryId === null) {
            $ids = $this->visibleIds($country);
        } else {
            $category = ClassifiedCategory::find($categoryId);
            $ids = $category ? $this->visibleBranchIds($category, $country) : [];
        }

        return ClassifiedListing::query()
            ->where('status', ClassifiedListingStatus::Active->value)
            ->whereIn('classified_category_id', $ids)
            ->when($country, fn (Builder $query) => $query->where('country_id', $country->id));
    }

    /**
     * A listing may only be created in a category that is active, whose ancestors are active and that is offered
     * in the listing's country. Existing listings of a category that is switched off later stay untouched.
     *
     * @throws ValidationException
     */
    public function assertListable(ClassifiedCategory $category, ?string $countryId): void
    {
        $country = $countryId ? Country::find($countryId) : null;

        if (! $this->isVisible($category->id, $country)) {
            throw ValidationException::withMessages([
                'classified_category_id' => __('classified.api.category_unavailable'),
            ]);
        }
    }

    /**
     * Home-page style blocks: visible sections for a placement, each with its newest ads in the visitor's country.
     *
     * @return list<array<string, mixed>>
     */
    public function sections(Country $country, ClassifiedPlacement $placement, int $sectionLimit, int $listingLimit, ListingQueryService $listings): array
    {
        return $this->tree($country, $placement)
            ->take($sectionLimit)
            ->map(function (ClassifiedCategory $section) use ($country, $listingLimit, $listings): array {
                $query = $this->publicListings($country, $section->id);
                $total = (clone $query)->count();
                $ads = $query->with(['images', 'city'])->orderByDesc('created_at')->limit($listingLimit)->get();

                return $this->present($section) + [
                    'total' => $total,
                    'listings' => $ads->map(fn (ClassifiedListing $listing) => $listings->toClassifiedCardShape($listing))->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Storefront shape of a category (sub-categories included when loaded).
     *
     * @return array<string, mixed>
     */
    public function present(ClassifiedCategory $category): array
    {
        return [
            'id' => $category->id,
            'type' => 'classified',
            'name' => ['ar' => $category->name_ar, 'en' => $category->name_en],
            'slug' => $category->slug,
            'parent_id' => $category->parent_id,
            'icon' => $category->icon,
            'image_url' => $category->image_url,
            'description' => ['ar' => $category->description_ar, 'en' => $category->description_en],
            'placements' => $category->placementList(),
            'children' => $category->relationLoaded('children')
                ? $category->children->map(fn (ClassifiedCategory $child) => $this->present($child->setRelation('children', collect())))->values()->all()
                : [],
        ];
    }
}
