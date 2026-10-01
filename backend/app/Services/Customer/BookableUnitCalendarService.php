<?php

namespace App\Services\Customer;

use App\Models\BookableUnit;
use App\Models\BookableUnitAvailability;
use Illuminate\Support\Carbon;

class BookableUnitCalendarService
{
    /**
     * A month's calendar for a unit: date, availability, capacity and both
     * prices, for the customer-facing calendar widget.
     *
     * @return array<int, array{date: string, is_available: bool, capacity: int, price_day_only: int|null, price_with_overnight: int|null}>
     */
    public function calendarForMonth(BookableUnit $unit, Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $rows = $unit->availability()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn (BookableUnitAvailability $row) => $row->date->toDateString());

        $days = [];
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $row = $rows->get($day->toDateString());

            $days[] = [
                'date' => $day->toDateString(),
                'is_available' => $row?->is_available ?? false,
                'capacity' => $row?->capacity_override ?? $unit->capacity,
                'price_day_only' => $row?->price_day_only,
                'price_with_overnight' => $row?->price_with_overnight,
            ];
        }

        return $days;
    }
}
