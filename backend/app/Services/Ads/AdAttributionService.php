<?php

namespace App\Services\Ads;

use App\Enums\PaidAdAdvertiserType;
use App\Models\MarketerCampaignInvitation;
use App\Models\PaidAdBooking;

class AdAttributionService
{
    /**
     * Attribution card data for a marketer's ad booking whose creative destination
     * is a campaign referral link: clicks/spend on the ad vs. conversions earned
     * through the linked invitation during the booking's live window.
     */
    public function forBooking(PaidAdBooking $booking): ?array
    {
        if ($booking->advertiser_type !== PaidAdAdvertiserType::Marketer) {
            return null;
        }

        $creative = $booking->currentCreative ?? $booking->creatives?->firstWhere('is_current', true);

        if (! $creative || $creative->destination_type !== 'campaign' || ! $creative->destination_reference_id) {
            return null;
        }

        $invitation = MarketerCampaignInvitation::where('campaign_id', $creative->destination_reference_id)
            ->where('marketer_id', $booking->marketer_id)
            ->where('status', 'accepted')
            ->first();

        if (! $invitation) {
            return null;
        }

        $windowStart = $booking->started_at ?? $booking->booked_from;
        $windowEnd = $booking->completed_at ?? now();

        $conversions = $invitation->conversions()
            ->when($windowStart, fn ($q) => $q->where('referral_clicked_at', '>=', $windowStart))
            ->when($windowEnd, fn ($q) => $q->where('referral_clicked_at', '<=', $windowEnd))
            ->get();

        $commissionEarned = (int) $conversions->sum('commission_amount');
        $adSpend = (int) ($booking->total_charged ?: ($booking->quoted_amount + $booking->tax_amount));

        return [
            'invitation_id' => $invitation->id,
            'referral_code' => $invitation->referral_code,
            'window' => [
                'from' => $windowStart?->toISOString(),
                'to' => $windowEnd?->toISOString(),
            ],
            'conversions_count' => $conversions->count(),
            'commission_earned' => $commissionEarned,
            'ad_spend' => $adSpend,
            'currency' => $booking->currency,
            'roi_percent' => $adSpend > 0 ? round(($commissionEarned - $adSpend) / $adSpend * 100, 1) : null,
        ];
    }
}
