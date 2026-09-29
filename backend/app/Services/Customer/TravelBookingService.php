<?php

namespace App\Services\Customer;

use App\Enums\TravelBookingStatus;
use App\Enums\TravelPackageStatus;
use App\Jobs\NotifyTravelBookingJob;
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
            ->with(['package.media', 'package.agency:id,name'])
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

        $booking = DB::transaction(function () use ($package, $customer, $data, $travelersCount) {
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

            return TravelBooking::create([
                'travel_package_id' => $package->id,
                'customer_id' => $customer->id,
                'travelers_count' => $travelersCount,
                'total_price' => $totalCents,
                'passport_file_path' => $passportPath,
                'status' => TravelBookingStatus::PendingDocuments,
            ]);
        });

        NotifyTravelBookingJob::dispatch($booking);

        // Notify the travel agency that payment has been received for this booking.
        $booking->loadMissing('package.agency');
        $booking->package->agency?->activeMembers()->each(
            fn ($member) => $member->notify(new PaymentReceived($booking))
        );

        return $booking;
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
