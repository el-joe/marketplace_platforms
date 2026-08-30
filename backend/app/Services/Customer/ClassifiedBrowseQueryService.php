<?php

namespace App\Services\Customer;

use App\Enums\ClassifiedListingStatus;
use App\Models\ClassifiedCategory;
use App\Models\ClassifiedListing;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ClassifiedBrowseQueryService
{
    /**
     * Recursively collect the category and all active descendant IDs
     * via adjacency-list walk (ClassifiedCategory has no nested-set).
     *
     * @return list<string>
     */
    public function getDescendantIds(ClassifiedCategory $category): array
    {
        $ids = [$category->id];
        $this->collectChildIds($category->id, $ids);

        return $ids;
    }

    public function paginate(
        array $categoryIds,
        array $filters,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $query = ClassifiedListing::query()
            ->with(['images', 'city', 'seller'])
            ->whereIn('classified_category_id', $categoryIds)
            ->where('status', ClassifiedListingStatus::Active->value);

        if (!empty($filters['price_min'])) {
            $query->where('price', '>=', (int) $filters['price_min']);
        }
        if (!empty($filters['price_max'])) {
            $query->where('price', '<=', (int) $filters['price_max']);
        }
        if (!empty($filters['listing_purpose'])) {
            $query->where('listing_purpose', $filters['listing_purpose']);
        }
        if (!empty($filters['city_id'])) {
            $query->where('city_id', $filters['city_id']);
        }

        $sort = $filters['sort'] ?? 'newest';
        $query = match ($sort) {
            'price_asc'  => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            default      => $query->orderBy('created_at', 'desc'),
        };

        return $query->paginate($perPage);
    }

    public function facets(array $categoryIds, array $filters): array
    {
        $base = ClassifiedListing::whereIn('classified_category_id', $categoryIds)
            ->where('status', ClassifiedListingStatus::Active->value);

        if (!empty($filters['listing_purpose'])) {
            $base->where('listing_purpose', $filters['listing_purpose']);
        }
        if (!empty($filters['city_id'])) {
            $base->where('city_id', $filters['city_id']);
        }

        $range = (clone $base)
            ->selectRaw('MIN(price) as low, MAX(price) as high')
            ->first();

        return [
            'price_range' => [
                'min' => $range ? (int) $range->low : 0,
                'max' => $range ? (int) $range->high : 0,
            ],
        ];
    }

    private function collectChildIds(string $parentId, array &$ids): void
    {
        $children = ClassifiedCategory::where('parent_id', $parentId)
            ->where('is_active', true)
            ->pluck('id');

        foreach ($children as $childId) {
            $ids[] = $childId;
            $this->collectChildIds($childId, $ids);
        }
    }
}
