<?php

namespace App\Services\Customer;

use App\Enums\TravelPackageStatus;
use App\Models\TravelCategory;
use App\Models\TravelPackage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TravelBrowseQueryService
{
    /**
     * @return list<string>
     */
    public function getDescendantIds(TravelCategory $category): array
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
        $today = now()->toDateString();

        $query = TravelPackage::query()
            ->with(['media', 'agency'])
            ->whereHas('categories', fn ($q) => $q->whereIn('travel_categories.id', $categoryIds))
            ->where('status', TravelPackageStatus::Active)
            ->whereDate('departure_date', '>=', $today)
            ->where(function ($q) {
                $q->whereNull('available_seats')
                  ->orWhereColumn('seats_booked', '<', 'available_seats');
            });

        if (!empty($filters['price_min'])) {
            $query->where('price', '>=', (int) $filters['price_min']);
        }
        if (!empty($filters['price_max'])) {
            $query->where('price', '<=', (int) $filters['price_max']);
        }
        if (!empty($filters['duration_min'])) {
            $query->where('duration_days', '>=', (int) $filters['duration_min']);
        }
        if (!empty($filters['duration_max'])) {
            $query->where('duration_days', '<=', (int) $filters['duration_max']);
        }
        if (!empty($filters['destination_country'])) {
            $query->where('destination_country', $filters['destination_country']);
        }
        if (!empty($filters['departure_from'])) {
            $query->whereDate('departure_date', '>=', $filters['departure_from']);
        }
        if (!empty($filters['departure_to'])) {
            $query->whereDate('departure_date', '<=', $filters['departure_to']);
        }

        $sort = $filters['sort'] ?? 'departure_date';
        $query = match ($sort) {
            'price_asc'      => $query->orderBy('price', 'asc'),
            'price_desc'     => $query->orderBy('price', 'desc'),
            'newest'         => $query->orderBy('created_at', 'desc'),
            default          => $query->orderBy('departure_date', 'asc'),
        };

        return $query->paginate($perPage);
    }

    public function facets(array $categoryIds, array $filters): array
    {
        $today = now()->toDateString();

        $base = TravelPackage::whereHas('categories', fn ($q) => $q->whereIn('travel_categories.id', $categoryIds))
            ->where('status', TravelPackageStatus::Active)
            ->whereDate('departure_date', '>=', $today)
            ->where(function ($q) {
                $q->whereNull('available_seats')
                  ->orWhereColumn('seats_booked', '<', 'available_seats');
            });

        if (!empty($filters['destination_country'])) {
            $base->where('destination_country', $filters['destination_country']);
        }

        $range = (clone $base)
            ->selectRaw('MIN(price) as low, MAX(price) as high, MIN(duration_days) as dur_min, MAX(duration_days) as dur_max')
            ->first();

        $destinations = (clone $base)
            ->distinct()
            ->orderBy('destination_country')
            ->pluck('destination_country')
            ->values()
            ->toArray();

        return [
            'price_range' => [
                'min' => $range ? (int) $range->low : 0,
                'max' => $range ? (int) $range->high : 0,
            ],
            'duration_range' => [
                'min' => $range ? (int) $range->dur_min : 0,
                'max' => $range ? (int) $range->dur_max : 0,
            ],
            'destination_countries' => $destinations,
        ];
    }

    private function collectChildIds(string $parentId, array &$ids): void
    {
        $children = TravelCategory::where('parent_id', $parentId)
            ->where('is_active', true)
            ->pluck('id');

        foreach ($children as $childId) {
            $ids[] = $childId;
            $this->collectChildIds($childId, $ids);
        }
    }
}
