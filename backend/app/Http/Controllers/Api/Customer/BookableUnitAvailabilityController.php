<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\BookableUnit\CreateReservationRequest;
use App\Http\Responses\ApiResponse;
use App\Models\BookableUnit;
use App\Models\Customer;
use App\Services\Customer\BookableUnitReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class BookableUnitAvailabilityController extends Controller
{
    public function __construct(private readonly BookableUnitReservationService $reservations) {}

    /**
     * GET /bookable-units/{unit}/calendar?month=YYYY-MM
     * Returns a month's calendar: date, is_available, capacity and both
     * prices, for the customer-facing booking widget.
     */
    public function calendar(Request $request, string $unit): JsonResponse
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

    /**
     * POST /bookable-units/{unit}/reservations (authenticated customer)
     * Creates a reservation. Double-booking is prevented inside the
     * service via a DB transaction + lockForUpdate() on the relevant
     * availability row(s).
     */
    public function reserve(CreateReservationRequest $request, string $unit): JsonResponse
    {
        $bookableUnit = BookableUnit::where('status', 'active')->find($unit);

        if (! $bookableUnit) {
            return ApiResponse::error(__('common.exceptions.listing.not_found'), [], 404);
        }

        /** @var Customer $customer */
        $customer = auth('customer')->user();

        $reservation = $this->reservations->reserve($bookableUnit, $customer, $request->validated());

        return ApiResponse::success([
            'id' => $reservation->id,
            'bookable_unit_id' => $reservation->bookable_unit_id,
            'date_from' => $reservation->date_from->toDateString(),
            'date_to' => $reservation->date_to->toDateString(),
            'time_slot_id' => $reservation->time_slot_id,
            'includes_overnight' => $reservation->includes_overnight,
            'total_price' => $reservation->total_price,
            'status' => $reservation->status->value,
        ], __('common.exceptions.listing.booking_submitted'), 201);
    }
}
