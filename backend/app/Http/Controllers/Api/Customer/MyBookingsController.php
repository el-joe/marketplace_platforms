<?php

namespace App\Http\Controllers\Api\Customer;

use App\DTOs\UnifiedBookingDTO;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\BookableUnitReservation;
use App\Models\FlightBooking;
use App\Models\TravelBooking;
use Illuminate\Http\JsonResponse;

class MyBookingsController extends Controller
{
    /**
     * GET /my-bookings — one merged list of travel packages, bookable units and flights.
     */
    public function index(): JsonResponse
    {
        $customer = auth('customer')->user();
        $bookings = collect();

        $bookings = $bookings->merge(
            TravelBooking::with('package.agency')
                ->where('customer_id', $customer->id)
                ->get()
                ->map(fn ($b) => new UnifiedBookingDTO(
                    id: $b->id,
                    type: 'travel_package',
                    bookingNumber: (string) ($b->booking_number ?? $b->id),
                    title: (string) ($b->package?->title_ar ?? $b->package?->title_en ?? ''),
                    dateFrom: (string) $b->package?->departure_date?->toDateString(),
                    dateTo: (string) $b->package?->return_date?->toDateString(),
                    totalPrice: (int) $b->total_price,
                    currency: (string) ($b->package?->currency ?? ''),
                    status: (string) ($b->status->value ?? $b->status),
                    agencyName: $b->package?->agency?->name,
                    thumbnailUrl: null,
                ))
        );

        $bookings = $bookings->merge(
            BookableUnitReservation::with('bookableUnit.agency')
                ->where('customer_id', $customer->id)
                ->get()
                ->map(fn ($r) => new UnifiedBookingDTO(
                    id: $r->id,
                    type: 'bookable_unit',
                    bookingNumber: (string) $r->reservation_number,
                    title: (string) ($r->bookableUnit?->name_ar ?? $r->bookableUnit?->name ?? ''),
                    dateFrom: $r->date_from->toDateString(),
                    dateTo: $r->date_to->toDateString(),
                    totalPrice: (int) $r->total_price,
                    currency: (string) $r->currency,
                    status: (string) ($r->status->value ?? $r->status),
                    agencyName: $r->bookableUnit?->agency?->name,
                    thumbnailUrl: null,
                ))
        );

        $bookings = $bookings->merge(
            FlightBooking::where('customer_id', $customer->id)
                ->get()
                ->map(fn ($f) => new UnifiedBookingDTO(
                    id: $f->id,
                    type: 'flight',
                    bookingNumber: $f->booking_number,
                    title: "{$f->origin_city} → {$f->destination_city}",
                    dateFrom: $f->departure_at->toIso8601String(),
                    dateTo: ($f->arrival_at ?? $f->departure_at)->toIso8601String(),
                    totalPrice: (int) $f->total_price,
                    currency: $f->currency,
                    status: $f->status,
                    agencyName: $f->airline_name,
                    thumbnailUrl: null,
                ))
        );

        return ApiResponse::success(
            $bookings->sortByDesc('dateFrom')->values()->map(fn ($b) => $b->toArray())
        );
    }
}
