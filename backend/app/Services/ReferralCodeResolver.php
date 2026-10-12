<?php

namespace App\Services;

use App\Models\MarketerCampaign;
use App\Models\MarketerCampaignInvitation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * P14: the single source of truth for "may this referral code still earn a commission?".
 *
 * Attribution matrix (invitation status x campaign status):
 *   accepted  + campaign active/auto_approved      => ACTIVE    always attributable
 *   pending   + campaign live                      => PENDING   clicks are remembered, not attributable until accepted
 *   rejected | timed_out | replaced (+ campaign live), closed less than
 *   `marketer_referral_grace_days` ago              => GRACE     attributable ONLY for a click recorded BEFORE the invitation closed
 *   rejected | timed_out | replaced, past the grace => EXPIRED   never attributable
 *   any invitation of a cancelled/done/paused/... campaign => CAMPAIGN_CLOSED never attributable
 *   unknown code                                    => UNKNOWN
 *
 * A reassigned ad gets a brand new invitation with a fresh code/QR (MarketerCampaignService::dispatchInvitation),
 * so scanning an old code after the replacement never credits the new marketer, and a click on the old code
 * before the replacement never credits the new one either: each click resolves to exactly one invitation.
 */
class ReferralCodeResolver
{
    public const STATE_ACTIVE = 'active';

    public const STATE_PENDING = 'pending';

    public const STATE_GRACE = 'grace';

    public const STATE_EXPIRED = 'expired';

    public const STATE_CAMPAIGN_CLOSED = 'campaign_closed';

    public const STATE_UNKNOWN = 'unknown';

    public const DEFAULT_GRACE_DAYS = 7;

    public const LIVE_CAMPAIGN_STATUSES = ['active', 'auto_approved'];

    public const CLOSED_INVITATION_STATUSES = ['rejected', 'timed_out', 'replaced'];

    public static function graceDays(): int
    {
        return max(0, (int) setting('marketer_referral_grace_days', self::DEFAULT_GRACE_DAYS));
    }

    public function find(string $code): ?MarketerCampaignInvitation
    {
        return MarketerCampaignInvitation::where('referral_code', $code)
            ->with(['campaign.vendorListing.productVariant.product', 'campaign.adminListing.productVariant.product', 'campaign.marketerListing.productVariant.product'])
            ->first();
    }

    /** The moment an invitation stopped being the live holder of its ad (reject / timeout / replacement). */
    public function closedAt(MarketerCampaignInvitation $invitation): ?Carbon
    {
        if (! in_array($invitation->status, self::CLOSED_INVITATION_STATUSES, true)) {
            return null;
        }

        return $invitation->responded_at ?? $invitation->updated_at;
    }

    public function graceUntil(MarketerCampaignInvitation $invitation): ?Carbon
    {
        $closed = $this->closedAt($invitation);

        return $closed ? $closed->copy()->addDays(self::graceDays()) : null;
    }

    public function stateFor(MarketerCampaignInvitation $invitation, ?Carbon $at = null): string
    {
        $at ??= now();
        $campaign = $invitation->campaign;

        if (! $campaign || ! in_array($campaign->status, self::LIVE_CAMPAIGN_STATUSES, true)) {
            return self::STATE_CAMPAIGN_CLOSED;
        }

        if ($invitation->status === 'accepted') {
            return self::STATE_ACTIVE;
        }

        if ($invitation->status === 'pending') {
            return self::STATE_PENDING;
        }

        $until = $this->graceUntil($invitation);
        if ($until && self::graceDays() > 0 && $at->lessThanOrEqualTo($until)) {
            return self::STATE_GRACE;
        }

        return self::STATE_EXPIRED;
    }

    /**
     * Does a click recorded at $clickedAt on this invitation's code earn the commission for an order placed now?
     */
    public function isAttributable(MarketerCampaignInvitation $invitation, ?Carbon $clickedAt, ?Carbon $at = null): bool
    {
        return match ($this->stateFor($invitation, $at)) {
            self::STATE_ACTIVE => true,
            self::STATE_GRACE => $clickedAt !== null && $clickedAt->lessThan($this->closedAt($invitation)),
            default => false,
        };
    }

    /** Should a NEW scan of this code be remembered for later attribution? Only live holders (and a pending one that may accept). */
    public function acceptsNewClicks(MarketerCampaignInvitation $invitation): bool
    {
        return in_array($this->stateFor($invitation), [self::STATE_ACTIVE, self::STATE_PENDING], true);
    }

    /** Slug of the promoted product, used to send a non-attributable scan to the product page. */
    public function productSlug(MarketerCampaign $campaign): ?string
    {
        $listing = $campaign->vendorListing ?? $campaign->adminListing ?? $campaign->marketerListing;

        return $listing?->productVariant?->product?->slug;
    }

    /**
     * Code history of a campaign for the admin page: each chain starts at an invitation that replaced nobody and
     * follows `replaced_invitation_id` forward. Orphaned links (deleted predecessor) start their own chain.
     *
     * @return Collection<int, Collection<int, array{invitation: MarketerCampaignInvitation, state: string, grace_until: ?Carbon, closed_at: ?Carbon}>>
     */
    public function chainsFor(MarketerCampaign $campaign): Collection
    {
        $invitations = $campaign->invitations()->with('marketer')->withCount('conversions')->orderBy('created_at')->get();
        $ids = $invitations->pluck('id')->all();
        $byPredecessor = $invitations->filter(fn ($i) => $i->replaced_invitation_id)->groupBy('replaced_invitation_id');

        $roots = $invitations->filter(fn ($i) => ! $i->replaced_invitation_id || ! in_array($i->replaced_invitation_id, $ids, true));

        return $roots->values()->map(function (MarketerCampaignInvitation $root) use ($byPredecessor, $campaign) {
            $chain = collect();
            $seen = [];
            $node = $root;
            while ($node && ! isset($seen[$node->id])) {
                $seen[$node->id] = true;
                $node->setRelation('campaign', $campaign);
                $chain->push([
                    'invitation' => $node,
                    'state' => $this->stateFor($node),
                    'grace_until' => $this->graceUntil($node),
                    'closed_at' => $this->closedAt($node),
                ]);
                $node = $byPredecessor->get($node->id)?->first();
            }

            return $chain;
        });
    }
}
