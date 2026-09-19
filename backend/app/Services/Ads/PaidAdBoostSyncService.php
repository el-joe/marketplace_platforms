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

        if ($booking->status === PaidAdBookingStatus::Active || $booking->status->isTerminal()) {
            app(ListingBoostService::class)->refresh($listing);

            Log::info('PaidAdBoostSyncService: boost refreshed', [
                'listing_id' => $listing->id,
                'booking_id' => $booking->id,
                'status' => $booking->status->value,
            ]);
        }

        // All other statuses (pending_review, draft, scheduled, paused, etc.)
        // — no boost change.
    }
}
