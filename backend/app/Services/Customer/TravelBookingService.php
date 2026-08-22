<?php

namespace App\Services\Customer;

use App\Enums\TravelBookingStatus;
use App\Jobs\NotifyTravelBookingJob;
use App\Notifications\Customer\TravelBookingCancelled as CustomerTravelBookingCancelled;
use App\Notifications\TravelAgency\BookingCancelled;
use App\Models\Customer;
use App\Models\TravelBooking;
use App\Models\TravelPackage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class TravelBookingService
{
    // ── Account management ────────────────────────────────────────────────────

    public function listForCustomer(Customer $customer, array $filters = []): \Illuminate\Contracts\Pagination\LengthAwarePaginator
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
        $booking->update(['status' => TravelBookingStatus::Cancelled]);

        $booking->loadMissing('package.agency');
        Notification::send($booking->package->agency->activeMembers(), new BookingCancelled($booking, 'customer'));
        $customer->notify(new CustomerTravelBookingCancelled($booking, 'customer'));

        return $booking->fresh();
    }

    // ── Booking creation (called from storefront) ─────────────────────────────

    public function book(TravelPackage $package, Customer $customer, array $data): TravelBooking
    {
        $travelersCount = (int) $data['travelers_count'];
        $totalCents     = $package->priceForTravelersCount($travelersCount);

        $passportPath = null;
        if (isset($data['passport_file']) && $data['passport_file'] instanceof UploadedFile) {
            $passportPath = $data['passport_file']->store('travel-bookings/passports', 'private');
        }

        $booking = TravelBooking::create([
            'travel_package_id'  => $package->id,
            'customer_id'        => $customer->id,
            'travelers_count'    => $travelersCount,
            'total_price'  => $totalCents,
            'passport_file_path' => $passportPath,
            'status'             => TravelBookingStatus::PendingDocuments,
        ]);

        NotifyTravelBookingJob::dispatch($booking);

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
            'contract_signed_at'      => now(),
        ]);

        return $booking->fresh();
    }
}
