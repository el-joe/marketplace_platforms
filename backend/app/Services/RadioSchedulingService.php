<?php

namespace App\Services;

use App\Enums\RadioScheduleSlotRecurrence;
use App\Models\RadioChannel;
use App\Models\RadioScheduleSlot;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class RadioSchedulingService
{
    private static array $dayMap = [
        'mon' => Carbon::MONDAY,
        'tue' => Carbon::TUESDAY,
        'wed' => Carbon::WEDNESDAY,
        'thu' => Carbon::THURSDAY,
        'fri' => Carbon::FRIDAY,
        'sat' => Carbon::SATURDAY,
        'sun' => Carbon::SUNDAY,
    ];

    public function getActiveSlot(RadioChannel $channel): ?RadioScheduleSlot
    {
        $now = now();

        return $channel->scheduleSlots()
            ->where('is_active', true)
            ->get()
            ->first(fn(RadioScheduleSlot $slot) => $this->isSlotActiveAt($slot, $now));
    }

    public function getUpcomingSchedule(RadioChannel $channel, int $days = 7): Collection
    {
        $slots    = $channel->scheduleSlots()->where('is_active', true)->get();
        $upcoming = collect();
        $start    = now();
        $end      = now()->addDays($days);

        foreach ($slots as $slot) {
            foreach ($this->expandSlot($slot, $start, $end) as $occurrence) {
                $upcoming->push($occurrence);
            }
        }

        return $upcoming->sortBy('starts_at')->values();
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    private function isSlotActiveAt(RadioScheduleSlot $slot, Carbon $at): bool
    {
        $startTime = $slot->starts_at->format('H:i:s');
        $endTime   = $slot->ends_at->format('H:i:s');

        return match ($slot->recurrence) {
            RadioScheduleSlotRecurrence::Once   => $at->between($slot->starts_at, $slot->ends_at),
            RadioScheduleSlotRecurrence::Daily  => $at->format('H:i:s') >= $startTime && $at->format('H:i:s') <= $endTime,
            RadioScheduleSlotRecurrence::Weekly => $this->matchesWeeklySlot($slot, $at),
            default  => false,
        };
    }

    private function matchesWeeklySlot(RadioScheduleSlot $slot, Carbon $at): bool
    {
        $days    = $slot->recurrence_days ?? [];
        $dayName = strtolower($at->format('D')); // mon, tue …

        if (! in_array($dayName, $days)) {
            return false;
        }

        $startTime = $slot->starts_at->format('H:i:s');
        $endTime   = $slot->ends_at->format('H:i:s');

        return $at->format('H:i:s') >= $startTime && $at->format('H:i:s') <= $endTime;
    }

    /** Expand a slot into concrete occurrences within the given window */
    private function expandSlot(RadioScheduleSlot $slot, Carbon $start, Carbon $end): array
    {
        if ($slot->recurrence === RadioScheduleSlotRecurrence::Once) {
            if ($slot->ends_at->greaterThan($start) && $slot->starts_at->lessThan($end)) {
                return [['slot' => $slot, 'starts_at' => $slot->starts_at, 'ends_at' => $slot->ends_at]];
            }
            return [];
        }

        $occurrences = [];
        $cursor      = $start->copy()->startOfDay();
        $slotStart   = $slot->starts_at;
        $slotEnd     = $slot->ends_at;

        while ($cursor->lessThan($end)) {
            if ($slot->recurrence === RadioScheduleSlotRecurrence::Daily) {
                $oStart = $cursor->copy()->setTimeFrom($slotStart);
                $oEnd   = $cursor->copy()->setTimeFrom($slotEnd);
                if ($oEnd->greaterThan($start)) {
                    $occurrences[] = ['slot' => $slot, 'starts_at' => $oStart, 'ends_at' => $oEnd];
                }
            } elseif ($slot->recurrence === RadioScheduleSlotRecurrence::Weekly) {
                $days    = $slot->recurrence_days ?? [];
                $dayName = strtolower($cursor->format('D'));
                if (in_array($dayName, $days)) {
                    $oStart = $cursor->copy()->setTimeFrom($slotStart);
                    $oEnd   = $cursor->copy()->setTimeFrom($slotEnd);
                    if ($oEnd->greaterThan($start)) {
                        $occurrences[] = ['slot' => $slot, 'starts_at' => $oStart, 'ends_at' => $oEnd];
                    }
                }
            }

            $cursor->addDay();
        }

        return $occurrences;
    }
}
