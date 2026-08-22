<?php

namespace App\Services\TravelAgency;

use App\Enums\CustomerStatus;
use App\Enums\TravelBookingStatus;
use App\Enums\TravelPackageStatus;
use App\Models\Customer;
use App\Models\TravelBooking;
use App\Models\TravelPackage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingCreationService
{
    /**
     * @param array{
     *     travel_package_id: string,
     *     travelers_count: int,
     *     customer_mode: string,
     *     customer_id?: string,
     *     new_name?: string,
     *     new_phone?: string,
     *     new_email?: string,
     * } $data
     */
    public function create(string $agencyId, array $data): TravelBooking
    {
        return DB::transaction(function () use ($agencyId, $data) {
            $pkg = TravelPackage::lockForUpdate()->findOrFail($data['travel_package_id']);

            if ($pkg->travel_agency_id !== $agencyId) {
                abort(403);
            }

            if ($pkg->status !== TravelPackageStatus::Active) {
                throw ValidationException::withMessages(['travelers_count' => 'الباقة لم تعد نشطة.']);
            }

            if ($pkg->available_seats !== null
                && ($pkg->seats_booked + $data['travelers_count']) > $pkg->available_seats
            ) {
                throw ValidationException::withMessages(['travelers_count' => 'لا توجد مقاعد كافية متاحة.']);
            }

            if (($data['customer_mode'] ?? 'existing') === 'existing') {
                $customer = Customer::findOrFail($data['customer_id']);
            } else {
                $customer = Customer::create([
                    'name'     => $data['new_name'],
                    'email'    => $data['new_email'],
                    'phone'    => $data['new_phone'],
                    'password' => Str::random(32), // agency-created; customer sets own password via forgot-password
                    'status'   => CustomerStatus::Active,
                ]);
            }

            $booking = TravelBooking::create([
                'travel_package_id' => $pkg->id,
                'customer_id'       => $customer->id,
                'travelers_count'   => $data['travelers_count'],
                'total_price' => $pkg->priceForTravelersCount((int) $data['travelers_count']),
                'status'            => TravelBookingStatus::PendingDocuments,
            ]);

            $pkg->increment('seats_booked', $data['travelers_count']);

            return $booking;
        });
    }
}
