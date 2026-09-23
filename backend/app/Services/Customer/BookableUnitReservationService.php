<?php

namespace App\Services\Customer;

use App\Enums\BookableUnitReservationStatus;
use App\Models\BookableUnit;
use App\Models\BookableUnitAvailability;
use App\Models\BookableUnitReservation;
use App\Models\BookableUnitTimeSlot;
use App\Models\Customer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookableUnitReservationService
{
    /**
     * A month's calendar for a unit: date, availability, capacity and both
     * prices, for the customer-facing calendar view.
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
                // A day with no availability row has not been opened by the
                // agency yet, so it is not bookable.
                'is_available' => $row?->is_available ?? false,
                'capacity' => $row?->capacity_override ?? $unit->capacity,
                'price_day_only' => $row?->price_day_only,
                'price_with_overnight' => $row?->price_with_overnight,
            ];
        }

        return $days;
    }

    /**
     * Creates a reservation for a date range (or, when time_slot_id is
     * given, a single-day slot booking), guarding against double-booking
     * with a row lock on the relevant availability rows inside a
     * transaction.
     *
     * Simplification (documented, not over-engineered per the task):
     * whole-day bookings and per-slot bookings are treated independently.
     * A whole-day booking consumes the day's availability row (marks it
     * unavailable) so no other whole-day or slot booking can be made for
     * that date. A slot booking does NOT touch the day's availability row
     * (so the same day can still be booked in another slot, or as a
     * whole day if the agency later decides to), but it does prevent the
     * same slot/date from being double-booked by locking against existing
     * confirmed/pending reservations for that exact slot + date.
     */
    public function reserve(BookableUnit $unit, Customer $customer, array $data): BookableUnitReservation
    {
        $dateFrom = Carbon::parse($data['date_from'])->startOfDay();
        $dateTo = Carbon::parse($data['date_to'])->startOfDay();
        $includesOvernight = (bool) ($data['includes_overnight'] ?? false);
        $timeSlotId = $data['time_slot_id'] ?? null;

        if ($timeSlotId !== null && ! $dateFrom->equalTo($dateTo)) {
            throw ValidationException::withMessages([
                'time_slot_id' => 'Time-slot reservations can only be made for a single day.',
            ]);
        }

        return DB::transaction(function () use ($unit, $customer, $dateFrom, $dateTo, $includesOvernight, $timeSlotId) {
            if ($timeSlotId !== null) {
                return $this->reserveTimeSlot($unit, $customer, $dateFrom, $timeSlotId);
            }

            return $this->reserveWholeDayRange($unit, $customer, $dateFrom, $dateTo, $includesOvernight);
        });
    }

    private function reserveWholeDayRange(
        BookableUnit $unit,
        Customer $customer,
        Carbon $dateFrom,
        Carbon $dateTo,
        bool $includesOvernight,
    ): BookableUnitReservation {
        $dates = [];
        for ($d = $dateFrom->copy(); $d->lte($dateTo); $d->addDay()) {
            $dates[] = $d->toDateString();
        }

        // Lock every availability row in the requested range for the
        // duration of the transaction — this is what prevents two
        // concurrent requests from both seeing "available" and both
        // creating a reservation for the same day.
        $rows = BookableUnitAvailability::where('bookable_unit_id', $unit->id)
            ->whereIn('date', $dates)
            ->lockForUpdate()
            ->get()
            ->keyBy(fn (BookableUnitAvailability $row) => $row->date->toDateString());

        $totalPrice = 0;

        foreach ($dates as $date) {
            $row = $rows->get($date);

            if (! $row || ! $row->is_available) {
                throw ValidationException::withMessages([
                    'date_from' => "The unit is not available on {$date}.",
                ]);
            }

            $price = $includesOvernight ? $row->price_with_overnight : $row->price_day_only;

            if ($price === null) {
                throw ValidationException::withMessages([
                    'date_from' => "No price is configured for {$date}.",
                ]);
            }

            $totalPrice += $price;
        }

        // Consume the days: a whole-day booking occupies the single
        // physical unit for that date, so it's marked unavailable for
        // any further whole-day or slot booking.
        BookableUnitAvailability::where('bookable_unit_id', $unit->id)
            ->whereIn('date', $dates)
            ->update(['is_available' => false]);

        return BookableUnitReservation::create([
            'bookable_unit_id' => $unit->id,
            'customer_id' => $customer->id,
            'date_from' => $dateFrom->toDateString(),
            'date_to' => $dateTo->toDateString(),
            'time_slot_id' => null,
            'includes_overnight' => $includesOvernight,
            'total_price' => $totalPrice,
            'status' => BookableUnitReservationStatus::Pending,
        ]);
    }

    private function reserveTimeSlot(
        BookableUnit $unit,
        Customer $customer,
        Carbon $date,
        string $timeSlotId,
    ): BookableUnitReservation {
        /** @var BookableUnitTimeSlot|null $slot */
        $slot = BookableUnitTimeSlot::where('bookable_unit_id', $unit->id)
            ->lockForUpdate()
            ->find($timeSlotId);

        if (! $slot) {
            throw ValidationException::withMessages([
                'time_slot_id' => 'This time slot does not exist for the selected unit.',
            ]);
        }

        // Guard against double-booking the same slot on the same date:
        // lock and inspect existing non-cancelled reservations for this
        // exact slot + date within the same transaction.
        $alreadyBooked = BookableUnitReservation::where('bookable_unit_id', $unit->id)
            ->where('time_slot_id', $slot->id)
            ->where('date_from', $date->toDateString())
            ->whereIn('status', [BookableUnitReservationStatus::Pending, BookableUnitReservationStatus::Confirmed])
            ->lockForUpdate()
            ->exists();

        if ($alreadyBooked) {
            throw ValidationException::withMessages([
                'time_slot_id' => 'This time slot is already booked for the selected date.',
            ]);
        }

        return BookableUnitReservation::create([
            'bookable_unit_id' => $unit->id,
            'customer_id' => $customer->id,
            'date_from' => $date->toDateString(),
            'date_to' => $date->toDateString(),
            'time_slot_id' => $slot->id,
            'includes_overnight' => false,
            'total_price' => $slot->price,
            'status' => BookableUnitReservationStatus::Pending,
        ]);
    }
}
