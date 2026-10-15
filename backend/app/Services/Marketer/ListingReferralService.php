<?php

namespace App\Services\Marketer;

use App\Models\MarketerCampaignInvitation;
use App\Services\LastClickAttributionService;
use App\Services\ReferralCodeResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Attribution for the non-product verticals. A visitor who followed a marketer's link remembers it per session
 * (LastClickAttributionService::recordClick); when the visitor later sends a classified inquiry or books a trip, this
 * service says which marketer, campaign and code to stamp on the new row.
 *
 * A click only counts when it is still inside the attribution window, its invitation is still attributable
 * (ReferralCodeResolver) and, crucially, the campaign promotes THIS listing or trip: a click on a marketer's link for
 * trip A never credits a booking of trip B.
 */
class ListingReferralService
{
    public function __construct(
        private readonly LastClickAttributionService $clicks,
        private readonly ReferralCodeResolver $resolver,
    ) {}

    /** The visitor's session id as the storefront sends it (same lookup the click tracking uses). */
    public static function sessionIdFrom(Request $request): ?string
    {
        return $request->header('X-Session-Id')
            ?? $request->query('session_id')
            ?? $request->cookie('session_id');
    }

    /**
     * @param  string  $vertical  'classified' | 'travel'
     * @param  string  $itemId  classified_listings.id or travel_packages.id
     * @return array{invitation: MarketerCampaignInvitation, referral_code: string, marketer_id: string, marketer_campaign_id: string, clicked_at: ?Carbon}|null
     */
    public function resolve(string $vertical, string $itemId, ?string $sessionId): ?array
    {
        $click = $this->clicks->clickFor($sessionId, $vertical);
        if (! $click || empty($click['referral_code'])) {
            return null;
        }

        $clickedAt = Carbon::parse($click['clicked_at']);
        $windowDays = (int) setting('marketer_attribution_window_days', LastClickAttributionService::DEFAULT_ATTRIBUTION_WINDOW_DAYS);
        if (now()->diffInDays($clickedAt) > $windowDays) {
            return null;
        }

        $invitation = MarketerCampaignInvitation::where('referral_code', $click['referral_code'])
            ->with('campaign')
            ->first();

        if (! $invitation || ! $invitation->campaign || ! $this->resolver->isAttributable($invitation, $clickedAt)) {
            return null;
        }

        $column = $vertical === 'travel' ? 'travel_package_id' : 'classified_listing_id';
        if ((string) $invitation->campaign->{$column} !== $itemId) {
            return null;
        }

        return [
            'invitation' => $invitation,
            'referral_code' => $invitation->referral_code,
            'marketer_id' => $invitation->marketer_id,
            'marketer_campaign_id' => $invitation->campaign_id,
            'clicked_at' => $clickedAt,
        ];
    }

    /**
     * The attribution columns to merge into a new classified inquiry / travel booking (empty when nobody is credited).
     *
     * @return array<string, string>
     */
    public function attributes(string $vertical, string $itemId, ?string $sessionId): array
    {
        $resolved = $this->resolve($vertical, $itemId, $sessionId);

        return $resolved
            ? array_intersect_key($resolved, array_flip(['marketer_id', 'marketer_campaign_id', 'referral_code']))
            : [];
    }
}
