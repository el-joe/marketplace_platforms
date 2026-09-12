<?php

namespace App\Services\Ads;

use App\Enums\PaidAdBookingStatus;
use App\Enums\PaidAdSlotTargetType;
use App\Models\PaidAdBooking;
use App\Models\VendorListing;
use Illuminate\Support\Facades\Log;

class PaidAdBoostSyncService
{
    /**
     * Called after every booking state transition in AdBookingService,
     * right alongside resolver->bust(). Only acts on listing_promotion
     * slots — all other slot types are a no-op.
     */
    public function sync(PaidAdBooking $booking): void
    {
        $booking->loadMissing('slot', 'currentCreative');

        if ($booking->slot?->target_type !== PaidAdSlotTargetType::ListingPromotion) {
            return;
        }

        $listingId = $booking->currentCreative?->destination_reference_id;

        if (! $listingId) {
            Log::warning('PaidAdBoostSyncService: no listing ID on booking creative', [
                'booking_id' => $booking->id,
            ]);

            return;
        }

        $listing = VendorListing::find($listingId);
        if (! $listing) {
            Log::warning('PaidAdBoostSyncService: listing not found', [
                'listing_id' => $listingId,
                'booking_id' => $booking->id,
            ]);

            return;
        }

        if ($booking->status === PaidAdBookingStatus::Active) {
            $listing->update([
                'is_ad_boosted' => true,
                'ad_boost_expires_at' => $booking->booked_until,
            ]);

            Log::info('PaidAdBoostSyncService: boost activated', [
                'listing_id' => $listing->id,
                'booking_id' => $booking->id,
                'expires_at' => $booking->booked_until,
            ]);

            return;
        }

        if ($booking->status->isTerminal()) {
            // Only clear if no other active listing_promotion booking targets
            // the same listing (guard against overlapping bookings).
            $hasOtherActiveBooking = PaidAdBooking::where('id', '!=', $booking->id)
                ->where('status', PaidAdBookingStatus::Active->value)
                ->whereHas('slot', fn ($q) => $q->where('target_type', PaidAdSlotTargetType::ListingPromotion->value))
                ->whereHas('currentCreative', fn ($q) => $q->where('destination_reference_id', $listingId))
                ->exists();

            if (! $hasOtherActiveBooking) {
                $listing->update([
                    'is_ad_boosted' => false,
                    'ad_boost_expires_at' => null,
                ]);

                Log::info('PaidAdBoostSyncService: boost cleared', [
                    'listing_id' => $listing->id,
                    'booking_id' => $booking->id,
                    'reason' => $booking->status->value,
                ]);
            }
        }

        // All other statuses (pending_review, draft, scheduled, paused, etc.)
        // — no boost change.
    }
}
