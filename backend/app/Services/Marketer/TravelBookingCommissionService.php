<?php

namespace App\Services\Marketer;

use App\Enums\TravelBookingStatus;
use App\Models\MarketerCampaignConversion;
use App\Models\MarketerCampaignInvitation;
use App\Models\TravelBooking;
use Illuminate\Support\Facades\Log;

/**
 * Keeps the marketer commission of a trip booking in step with the booking.
 *
 * A booking made through a marketer's link carries marketer_id / marketer_campaign_id / referral_code (stamped when it
 * was created, see ListingReferralService). Then:
 *
 *   confirmed (or completed)  an affiliate / broker earns a conversion through CampaignSaleRecorder
 *                             (ConversionSource::travelBooking, basis = the booking total, one per booking).
 *                             Influencers never earn per booking: their fixed amount is paid at campaign completion.
 *                             The conversion stays pending; ApproveMarketerConversionsJob approves it once the trip has
 *                             taken place (CompleteTravelBookingsJob sets completed_at);
 *   cancelled                 the conversion is reversed (budget freed, postings undone). A commission that was already
 *                             paid out in a marketer payout is left for manual recovery.
 *
 * Bookings carry no separate payment status: an agency confirms a booking once the traveller has paid, so
 * "confirmed" is the paid signal.
 * TravelBooking::booted() calls sync() on every status change, so every entry point (customer, agency web and API
 * panels, storefront) behaves the same.
 */
class TravelBookingCommissionService
{
    public function __construct(private readonly CampaignSaleRecorder $recorder) {}

    public function sync(TravelBooking $booking): void
    {
        if (! $booking->marketer_campaign_id || ! $booking->marketer_id) {
            return;
        }

        try {
            match ($booking->status) {
                TravelBookingStatus::Confirmed, TravelBookingStatus::Completed => $this->credit($booking),
                TravelBookingStatus::Cancelled => $this->reverse($booking),
                default => null,
            };
        } catch (\Throwable $e) {
            // A commission problem must never block the booking itself; the nightly job and admins can reconcile.
            Log::error('Travel booking commission sync failed.', ['booking_id' => $booking->id, 'error' => $e->getMessage()]);
        }
    }

    public function credit(TravelBooking $booking): ?MarketerCampaignConversion
    {
        $invitation = MarketerCampaignInvitation::where('campaign_id', $booking->marketer_campaign_id)
            ->where('marketer_id', $booking->marketer_id)
            ->when($booking->referral_code, fn ($q) => $q->where('referral_code', $booking->referral_code))
            ->first();

        if (! $invitation) {
            return null;
        }

        return $this->recorder->record(
            $invitation,
            ConversionSource::travelBooking((string) $booking->id),
            (float) $booking->total_price,
            1,
            null,
            now(),
            $booking->created_at,
        );
    }

    public function reverse(TravelBooking $booking): bool
    {
        $conversion = ConversionSource::travelBooking((string) $booking->id)->existing();

        return $conversion !== null && $conversion->status !== 'reversed' && $this->recorder->reverse($conversion);
    }
}
