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
        if (!empty($filters['destination_country_id'])) {
            $query->where('destination_travel_country_id', $filters['destination_country_id']);
        }
        if (!empty($filters['destination_city_id'])) {
            $query->where('destination_travel_city_id', $filters['destination_city_id']);
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

        if (!empty($filters['destination_country_id'])) {
            $base->where('destination_travel_country_id', $filters['destination_country_id']);
        }
        if (!empty($filters['destination_city_id'])) {
            $base->where('destination_travel_city_id', $filters['destination_city_id']);
        }

        $range = (clone $base)
            ->selectRaw('MIN(price) as low, MAX(price) as high, MIN(duration_days) as dur_min, MAX(duration_days) as dur_max')
            ->first();

        $destinations = (clone $base)
            ->join('travel_countries', 'travel_countries.id', '=', 'travel_packages.destination_travel_country_id')
            ->select('travel_countries.id', 'travel_countries.name_en', 'travel_countries.name_ar')
            ->distinct()
            ->orderBy('travel_countries.name_en')
            ->get();

        $cities = (clone $base)
            ->whereNotNull('destination_travel_city_id')
            ->join('travel_cities', 'travel_cities.id', '=', 'travel_packages.destination_travel_city_id')
            ->select('travel_cities.id', 'travel_cities.name_en', 'travel_cities.name_ar')
            ->distinct()
            ->orderBy('travel_cities.name_en')
            ->get();

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
            'destination_cities' => $cities,
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
