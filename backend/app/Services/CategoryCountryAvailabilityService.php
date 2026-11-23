<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Country;
use App\Models\CountryCategory;
use Illuminate\Support\Collection;

/**
 * Category availability per country, read from and written to the existing
 * `country_categories` exceptions (the same rows the country page edits under
 * "Category exceptions"). There is no separate store: a missing row or
 * `is_available = 1` means available, `is_available = 0` means unavailable.
 *
 * The category form's mode (all / only selected / all except selected) is
 * derived from those rows on read and translated back into rows on write, so
 * whichever screen saved last wins and both screens always show the same data.
 *
 * Effective availability is inherited downwards: a category is reachable in a
 * country only when it and every ancestor are active and not excluded there.
 */
class CategoryCountryAvailabilityService
{
    public const MODE_ALL = 'all';

    public const MODE_ONLY = 'only';

    public const MODE_EXCEPT = 'except';

    public const MODES = [self::MODE_ALL, self::MODE_ONLY, self::MODE_EXCEPT];

    /**
     * Mode + selected countries as the category form shows them.
     *
     * @return array{mode: string, country_ids: list<string>}
     */
    public function describe(?Category $category): array
    {
        if ($category === null) {
            return ['mode' => self::MODE_ALL, 'country_ids' => []];
        }

        $countryIds = Country::query()->pluck('id');
        $unavailable = CountryCategory::query()
            ->where('category_id', $category->id)
            ->where('is_available', false)
            ->whereIn('country_id', $countryIds)
            ->pluck('country_id');

        if ($unavailable->isEmpty()) {
            return ['mode' => self::MODE_ALL, 'country_ids' => []];
        }

        $available = $countryIds->diff($unavailable)->values();

        // Show whichever list is shorter: "only Oman" reads better than "all except 7 countries".
        return $available->count() < $unavailable->count()
            ? ['mode' => self::MODE_ONLY, 'country_ids' => $available->all()]
            : ['mode' => self::MODE_EXCEPT, 'country_ids' => $unavailable->values()->all()];
    }

    /**
     * Writes the form's mode as country exceptions. Commission overrides and
     * notes on existing rows are kept; only `is_available` changes.
     *
     * @param  list<string>  $selectedCountryIds
     */
    public function sync(Category $category, string $mode, array $selectedCountryIds, ?string $adminId = null): void
    {
        $selected = collect($selectedCountryIds)->unique()->values();

        $existing = CountryCategory::query()
            ->where('category_id', $category->id)
            ->get()
            ->keyBy('country_id');

        foreach (Country::query()->pluck('id') as $countryId) {
            $available = match ($mode) {
                self::MODE_ONLY => $selected->contains($countryId),
                self::MODE_EXCEPT => ! $selected->contains($countryId),
                default => true,
            };

            $row = $existing->get($countryId);

            // No row already means "available": don't create rows just to say so.
            if ($row === null && $available) {
                continue;
            }

            if ($row !== null && (bool) $row->is_available === $available) {
                continue;
            }

            CountryCategory::updateOrCreate(
                ['country_id' => $countryId, 'category_id' => $category->id],
                ['is_available' => $available, 'updated_by_admin_id' => $adminId],
            );
        }
    }

    /**
     * Ids of the category's parent chain (closest first), excluding itself.
     *
     * @return list<string>
     */
    public function ancestorIds(Category $category): array
    {
        $ids = [];
        $parentId = $category->parent_id;

        while ($parentId !== null && ! in_array($parentId, $ids, true) && count($ids) < 20) {
            $ids[] = $parentId;
            $parentId = Category::query()->whereKey($parentId)->value('parent_id');
        }

        return $ids;
    }

    /**
     * Countries where an ancestor already hides the category (inherited exclusion).
     *
     * @return Collection<int, string>
     */
    public function inheritedUnavailableCountryIds(Category $category): Collection
    {
        $ancestorIds = $this->ancestorIds($category);

        if ($ancestorIds === []) {
            return collect();
        }

        return CountryCategory::query()
            ->whereIn('category_id', $ancestorIds)
            ->where('is_available', false)
            ->distinct()
            ->pluck('country_id');
    }

    /**
     * Storefront gate: the category page (and its product grid) exists only when
     * the category and all its ancestors are active, not deleted, and not excluded
     * for this country.
     */
    public function isAccessibleIn(Category $category, Country $country): bool
    {
        if (! $category->is_active || $category->trashed()) {
            return false;
        }

        // Without nested-set bounds fall back to walking parent_id.
        if (! $category->getLft() || ! $category->getRgt()) {
            return $this->reachabilityCheck($country)($category->id);
        }

        // One query: the category and its whole ancestor chain (nested-set range),
        // each with this country's exception, including soft-deleted ancestors.
        $chain = Category::withTrashed()
            ->leftJoin('country_categories', function ($join) use ($country) {
                $join->on('country_categories.category_id', '=', 'categories.id')
                    ->where('country_categories.country_id', $country->id);
            })
            ->where('categories.lft', '<=', $category->getLft())
            ->where('categories.rgt', '>=', $category->getRgt())
            ->get(['categories.id', 'categories.is_active', 'categories.deleted_at', 'country_categories.is_available']);

        return $chain->every(fn ($node) => $node->is_active
            && $node->deleted_at === null
            && ($node->is_available === null || (bool) $node->is_available));
    }

    /**
     * Bulk variant of {@see isAccessibleIn()} for lists (one query):
     * returns a checker telling whether a category id is reachable in $country.
     *
     * @return \Closure(string): bool
     */
    public function reachabilityCheck(Country $country): \Closure
    {
        $nodes = Category::query()
            ->leftJoin('country_categories', function ($join) use ($country) {
                $join->on('country_categories.category_id', '=', 'categories.id')
                    ->where('country_categories.country_id', $country->id);
            })
            ->get(['categories.id', 'categories.parent_id', 'categories.is_active', 'country_categories.is_available'])
            ->keyBy('id');
        $excluded = $nodes->filter(fn ($node) => $node->is_available !== null && ! $node->is_available)->keys()->flip()->all();

        return function (string $categoryId) use ($nodes, $excluded): bool {
            $id = $categoryId;
            for ($depth = 0; $id !== null && $depth < 20; $depth++) {
                $node = $nodes->get($id);
                if (! $node || ! $node->is_active || isset($excluded[$id])) {
                    return false;
                }
                $id = $node->parent_id;
            }

            return $id === null;
        };
    }

    /**
     * Category ids excluded for a country by an explicit exception row.
     *
     * @return list<string>
     */
    public function unavailableCategoryIds(Country $country): array
    {
        return CountryCategory::query()
            ->where('country_id', $country->id)
            ->where('is_available', false)
            ->pluck('category_id')
            ->all();
    }
}
