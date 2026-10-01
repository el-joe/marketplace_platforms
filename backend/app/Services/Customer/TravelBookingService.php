<?php

namespace App\Services\Customer;

use App\Enums\TravelBookingStatus;
use App\Enums\TravelPackageStatus;
use App\Jobs\NotifyTravelBookingJob;
use App\Models\BookableUnit;
use App\Models\BookableUnitAvailability;
use App\Models\BookableUnitTimeSlot;
use App\Models\BookingUnitDay;
use App\Models\Customer;
use App\Models\TravelBooking;
use App\Models\TravelPackage;
use App\Notifications\Customer\TravelBookingCancelled as CustomerTravelBookingCancelled;
use App\Notifications\TravelAgency\BookingCancelled;
use App\Notifications\TravelAgency\PaymentReceived;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class TravelBookingService
{
    // ── Account management ────────────────────────────────────────────────────

    public function listForCustomer(Customer $customer, array $filters = []): LengthAwarePaginator
    {
        return $customer->travelBookings()
            ->with(['package.media', 'package.agency:id,name'])
            ->when(isset($filters['status']), fn ($q) => $q->where('status', TravelBookingStatus::from($filters['status'])))
            ->latest()
            ->paginate(15);
    }

    public function showForCustomer(Customer $customer, string $id): TravelBooking
    {
        return $customer->travelBookings()
            ->with(['package.media', 'package.agency:id,name', 'bookableUnit.photos', 'unitDays.timeSlot'])
            ->findOrFail($id);
    }

    public function cancel(Customer $customer, string $id, string $reason): TravelBooking
    {
        $booking = $customer->travelBookings()->findOrFail($id);

        if (! in_array($booking->status, [TravelBookingStatus::PendingDocuments, TravelBookingStatus::Confirmed], true)) {
            throw ValidationException::withMessages([
                'status' => 'This booking cannot be cancelled in its current state.',
            ]);
        }

        // No automatic refund logic or cancellation_reason column exists in
        // the schema — cancellation is marked and left for admin/agency review.
        DB::transaction(function () use ($booking) {
            $wasConfirmed = $booking->status === TravelBookingStatus::Confirmed;
            $booking->update(['status' => TravelBookingStatus::Cancelled]);
            if ($wasConfirmed) {
                $pkg = TravelPackage::lockForUpdate()->find($booking->travel_package_id);
                if ($pkg) {
                    $pkg->update([
                        'seats_booked' => max(0, $pkg->seats_booked - $booking->travelers_count),
                        ...($pkg->status === TravelPackageStatus::SoldOut ? ['status' => TravelPackageStatus::Active] : []),
                    ]);
                }
            }
        });

        $booking->loadMissing('package.agency');
        Notification::send($booking->package->agency->activeMembers(), new BookingCancelled($booking, 'customer'));
        $customer->notify(new CustomerTravelBookingCancelled($booking, 'customer'));

        return $booking->fresh();
    }

    // ── Booking creation (called from storefront) ─────────────────────────────

    public function book(TravelPackage $package, Customer $customer, array $data): TravelBooking
    {
        $travelersCount = (int) $data['travelers_count'];
        $unitId = $data['unit_id'] ?? null;
        $unitDays = $data['unit_days'] ?? [];

        if ($unitId) {
            $unitBelongsToPackage = BookableUnit::where('id', $unitId)
                ->where('travel_package_id', $package->id)
                ->where('status', 'active')
                ->exists();

            if (! $unitBelongsToPackage) {
                throw ValidationException::withMessages([
                    'unit_id' => __('travel.bookable_unit_not_available'),
                ]);
            }
        }

        $booking = DB::transaction(function () use ($package, $customer, $data, $travelersCount, $unitId, $unitDays) {
            $pkg = TravelPackage::lockForUpdate()->findOrFail($package->id);
            if ($pkg->available_seats !== null
                && ($pkg->seats_booked + $travelersCount) > $pkg->available_seats
            ) {
                throw ValidationException::withMessages(['travelers_count' => 'Not enough seats available.']);
            }
            $totalCents = $pkg->priceForTravelersCount($travelersCount);

            $passportPath = null;
            if (isset($data['passport_file']) && $data['passport_file'] instanceof UploadedFile) {
                $passportPath = $data['passport_file']->store('travel-bookings/passports', 'private');
            }

            // Resolve unit days total and validate availability when unit is selected.
            $unitTotal = 0;
            $resolvedDays = [];
            if ($unitId && count($unitDays) > 0) {
                $unit = BookableUnit::where('status', 'active')->findOrFail($unitId);
                [$unitTotal, $resolvedDays] = $this->processUnitDays($unit, $unitDays);
                $totalCents += $unitTotal;
            }

            $booking = TravelBooking::create([
                'travel_package_id' => $package->id,
                'bookable_unit_id' => $unitId ?: null,
                'customer_id' => $customer->id,
                'travelers_count' => $travelersCount,
                'total_price' => $totalCents,
                'passport_file_path' => $passportPath,
                'status' => TravelBookingStatus::PendingDocuments,
            ]);

            foreach ($resolvedDays as $day) {
                BookingUnitDay::create([
                    'travel_booking_id' => $booking->id,
                    'bookable_unit_id' => $unitId,
                    'date' => $day['date'],
                    'includes_overnight' => $day['includes_overnight'],
                    'time_slot_id' => $day['time_slot_id'] ?? null,
                    'price' => $day['price'],
                ]);
            }

            return $booking;
        });

        NotifyTravelBookingJob::dispatch($booking);

        // Notify the travel agency that payment has been received for this booking.
        $booking->loadMissing('package.agency');
        $booking->package->agency?->activeMembers()->each(
            fn ($member) => $member->notify(new PaymentReceived($booking))
        );

        return $booking;
    }

    /**
     * Validates and prices an array of unit-day inputs against live availability rows.
     * Marks consumed days as unavailable. Returns [totalPrice, resolvedDays[]] inside
     * the caller's DB transaction.
     *
     * @param  array<int, array{date: string, includes_overnight?: bool, time_slot_id?: string}>  $unitDays
     * @return array{0: int, 1: list<array{date: string, includes_overnight: bool, time_slot_id: string|null, price: int}>}
     */
    private function processUnitDays(BookableUnit $unit, array $unitDays): array
    {
        $totalPrice = 0;
        $resolved = [];

        // Separate slot days from whole-day bookings.
        $wholeDayDates = [];
        $slotEntries = [];
        foreach ($unitDays as $entry) {
            if (! empty($entry['time_slot_id'])) {
                $slotEntries[] = $entry;
            } else {
                $wholeDayDates[] = $entry['date'];
            }
        }

        // ── Whole-day bookings ────────────────────────────────────────────────
        if (count($wholeDayDates) > 0) {
            $rows = BookableUnitAvailability::where('bookable_unit_id', $unit->id)
                ->whereIn('date', $wholeDayDates)
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (BookableUnitAvailability $r) => $r->date->toDateString());

            foreach ($wholeDayDates as $date) {
                $row = $rows->get($date);
                if (! $row || ! $row->is_available) {
                    throw ValidationException::withMessages([
                        'unit_days' => "The unit is not available on {$date}.",
                    ]);
                }
                // Find the matching entry to determine overnight preference.
                $entry = collect($unitDays)->firstWhere('date', $date);
                $overnight = (bool) ($entry['includes_overnight'] ?? false);
                $price = $overnight ? $row->price_with_overnight : $row->price_day_only;
                if ($price === null) {
                    throw ValidationException::withMessages([
                        'unit_days' => "No price configured for {$date}.",
                    ]);
                }
                $totalPrice += $price;
                $resolved[] = ['date' => $date, 'includes_overnight' => $overnight, 'time_slot_id' => null, 'price' => $price];
            }

            BookableUnitAvailability::where('bookable_unit_id', $unit->id)
                ->whereIn('date', $wholeDayDates)
                ->update(['is_available' => false]);
        }

        // ── Time-slot bookings ────────────────────────────────────────────────
        foreach ($slotEntries as $entry) {
            $slot = BookableUnitTimeSlot::where('bookable_unit_id', $unit->id)
                ->lockForUpdate()
                ->find($entry['time_slot_id']);

            if (! $slot) {
                throw ValidationException::withMessages([
                    'unit_days' => 'Time slot not found for the selected unit.',
                ]);
            }

            $alreadyBooked = BookingUnitDay::where('bookable_unit_id', $unit->id)
                ->where('time_slot_id', $slot->id)
                ->where('date', $entry['date'])
                ->lockForUpdate()
                ->exists();

            if ($alreadyBooked) {
                throw ValidationException::withMessages([
                    'unit_days' => "Time slot is already booked for {$entry['date']}.",
                ]);
            }

            $totalPrice += $slot->price;
            $resolved[] = ['date' => $entry['date'], 'includes_overnight' => false, 'time_slot_id' => $slot->id, 'price' => $slot->price];
        }

        return [$totalPrice, $resolved];
    }

    // ── Contract signing ──────────────────────────────────────────────────────

    public function signContract(Customer $customer, string $bookingNumber, string $signatureData): TravelBooking
    {
        $booking = $customer->travelBookings()
            ->where('booking_number', $bookingNumber)
            ->firstOrFail();

        if (! in_array($booking->status, [TravelBookingStatus::PendingDocuments, TravelBookingStatus::Confirmed], true)) {
            throw ValidationException::withMessages([
                'booking_number' => 'Contract cannot be signed in the current booking state.',
            ]);
        }

        $booking->update([
            'contract_signature_data' => $signatureData,
            'contract_signed_at' => now(),
        ]);

        return $booking->fresh();
    }
}
