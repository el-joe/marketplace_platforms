<?php

namespace App\Services\Ads;

use App\Enums\PaidAdBookingStatus;
use App\Enums\PaidAdSlotTargetType;
use App\Models\PaidAdBooking;
use App\Models\VendorListing;

class ListingBoostService
{
    /**
     * Recompute is_ad_boosted / ad_boost_expires_at from active
     * listing_promotion bookings.
     */
    public function refresh(VendorListing $listing): void
    {
        $bookingEnds = PaidAdBooking::where('status', PaidAdBookingStatus::Active->value)
            ->whereHas('slot', fn ($q) => $q->where('target_type', PaidAdSlotTargetType::ListingPromotion->value))
            ->whereHas('currentCreative', fn ($q) => $q->where('destination_reference_id', $listing->id))
            ->where(fn ($q) => $q->whereNull('booked_until')->orWhere('booked_until', '>=', now()->startOfDay()))
            ->pluck('booked_until')
            ->all();

        $all = $bookingEnds;

        if (! $all) {
            $listing->forceFill(['is_ad_boosted' => false, 'ad_boost_expires_at' => null])->save();

            return;
        }

        // A null end (open-ended booking) means no expiry.
        $expires = in_array(null, $all, true)
            ? null
            : collect($all)->max();

        $listing->forceFill(['is_ad_boosted' => true, 'ad_boost_expires_at' => $expires])->save();
    }
}
