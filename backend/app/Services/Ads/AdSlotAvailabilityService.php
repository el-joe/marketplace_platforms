<?php

namespace App\Services\Ads;

use App\Enums\PaidAdBookingStatus;
use App\Models\PaidAdSlot;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use DomainException;

class AdSlotAvailabilityService
{
    /**
     * @return array<string, int> date (Y-m-d) => number of overlapping bookings
     */
    public function dailyOccupancy(PaidAdSlot $slot, Carbon $from, Carbon $to, ?string $excludeBookingId = null): array
    {
        $statuses = array_map(fn (PaidAdBookingStatus $s) => $s->value, PaidAdBookingStatus::holdingStatuses());

        $bookings = $slot->bookings()
            ->whereIn('status', $statuses)
            ->where('booked_from', '<=', $to->toDateString())
            ->where('booked_until', '>=', $from->toDateString())
            ->when($excludeBookingId, fn ($q) => $q->where('id', '!=', $excludeBookingId))
            ->get(['id', 'booked_from', 'booked_until']);

        $occupancy = [];
        foreach (CarbonPeriod::create($from->copy()->startOfDay(), $to->copy()->startOfDay()) as $day) {
            $key = $day->toDateString();
            $occupancy[$key] = 0;
            foreach ($bookings as $booking) {
                $bookedFrom = Carbon::parse($booking->booked_from);
                $bookedUntil = Carbon::parse($booking->booked_until);
                if ($bookedFrom->lte($day) && $bookedUntil->gte($day)) {
                    $occupancy[$key]++;
                }
            }
        }

        return $occupancy;
    }

    /**
     * MUST be called inside a transaction after locking the slot row
     * (PaidAdSlot::whereKey($slot->id)->lockForUpdate()->first()).
     */
    public function assertAvailable(PaidAdSlot $slot, Carbon $from, Carbon $to, ?string $excludeBookingId = null): void
    {
        $occupancy = $this->dailyOccupancy($slot, $from, $to, $excludeBookingId);

        foreach ($occupancy as $date => $count) {
            if ($count >= $slot->max_concurrent) {
                throw new DomainException(__('ads.errors.slot_unavailable', ['date' => $date]));
            }
        }
    }

    /**
     * First date (on/after lead time) with free capacity, scanning up to $horizonDays ahead.
     * Used for marketplace "next available" display; returns null if nothing is free in range.
     */
    public function nextAvailableDate(PaidAdSlot $slot, int $horizonDays = 90): ?string
    {
        $country = $slot->country;
        $today = Carbon::now($country->timezone ?? 'UTC')->startOfDay();
        $from = $today->copy()->addDays($slot->lead_time_days);
        $to = $from->copy()->addDays($horizonDays);

        $occupancy = $this->dailyOccupancy($slot, $from, $to);

        foreach ($occupancy as $date => $count) {
            if ($count < $slot->max_concurrent) {
                return $date;
            }
        }

        return null;
    }

    /**
     * @return array<int, array{date: string, booked: int, capacity: int, available: bool}>
     */
    public function calendar(PaidAdSlot $slot, Carbon $month): array
    {
        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth();

        $occupancy = $this->dailyOccupancy($slot, $from, $to);

        $calendar = [];
        foreach ($occupancy as $date => $booked) {
            $calendar[] = [
                'date' => $date,
                'booked' => $booked,
                'capacity' => $slot->max_concurrent,
                'available' => $booked < $slot->max_concurrent,
            ];
        }

        return $calendar;
    }
}
