<?php

namespace App\Services\Ads;

use App\Enums\PaidAdAdvertiserType;
use App\Models\Admin;
use App\Models\PaidAdBooking;
use Illuminate\Support\Collection;

class AdBookingRecipients
{
    /** Admins allowed to review paid ad bookings. */
    public static function reviewers(): Collection
    {
        return Admin::permission('ad_bookings.review')->get();
    }

    /** Vendor or marketer admins who own the booking's advertiser account. */
    public static function advertiserAdmins(PaidAdBooking $b): Collection
    {
        if ($b->advertiser_type === PaidAdAdvertiserType::Vendor) {
            return $b->vendor
                ? $b->vendor->vendorAdmins()->where('is_active', 1)->get()
                : collect();
        }

        if ($b->advertiser_type === PaidAdAdvertiserType::Marketer) {
            return $b->marketer
                ? $b->marketer->marketerAdmins()->where('is_active', 1)->get()
                : collect();
        }

        return collect();
    }
}
