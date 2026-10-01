<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\BookableUnit;
use App\Services\Customer\BookableUnitCalendarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class BookableUnitAvailabilityController extends Controller
{
    public function __construct(private readonly BookableUnitCalendarService $reservations) {}

    /**
     * GET /bookable-units/{unit}/calendar?month=YYYY-MM
     * Returns a month's calendar for the customer-facing booking widget.
     */
    public function calendar(Request $request, $country, string $unit): JsonResponse
    {
        $bookableUnit = BookableUnit::where('status', 'active')->find($unit);

        if (! $bookableUnit) {
            return ApiResponse::error(__('common.exceptions.listing.not_found'), [], 404);
        }

        $monthInput = $request->query('month');
        $month = $monthInput ? Carbon::parse($monthInput.'-01') : now();

        $timeSlots = $bookableUnit->timeSlots()->orderBy('starts_at')->get(['id', 'slot_type', 'starts_at', 'ends_at', 'price']);

        return ApiResponse::success([
            'unit' => [
                'id' => $bookableUnit->id,
                'name' => $bookableUnit->name,
                'type' => $bookableUnit->type->value ?? $bookableUnit->type,
                'capacity' => $bookableUnit->capacity,
            ],
            'month' => $month->format('Y-m'),
            'days' => $this->reservations->calendarForMonth($bookableUnit, $month),
            'time_slots' => $timeSlots,
        ]);
    }
}
