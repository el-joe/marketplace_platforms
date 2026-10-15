<?php

namespace App\Services;

use App\Models\MarketerCampaignInvitation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Marketer\CampaignSaleRecorder;
use App\Services\Marketer\ConversionSource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * enhancement.md P-12: attribution + conversion recording, PER ORDER ITEM.
 *
 * Attribution priority (task 1):
 *   1. The order item was bought from a marketer listing
 *      (order_items.marketer_listing_id, set by P-02's CartLineSource). If
 *      that listing was created from an accepted campaign invitation
 *      (MarketerListing::invitation_id), that invitation gets the credit —
 *      the customer bought straight off the marketer's page, no referral
 *      click needed.
 *   2. Otherwise, last-click referral: a referral code cached against the
 *      session/customer at click time (recordClick), still within the
 *      attribution window (`marketer_attribution_window_days` setting,
 *      default 7 — see class const default below; no such setting existed
 *      before this prompt).
 *   3. Otherwise: no attribution, no conversion.
 *
 * The old implementation read `session('marketer_attribution', [])`, which
 * is always empty on the stateless JWT API, and only ever looked at
 * last-click. Both are gone; `Order::create(['marketer_id' => ...])` in
 * CheckoutController wrote non-existent/non-fillable columns and has been
 * removed there.
 */
class LastClickAttributionService
{
    /** Default attribution window when no `marketer_attribution_window_days` setting exists. */
    public const DEFAULT_ATTRIBUTION_WINDOW_DAYS = 7;

    private readonly ReferralCodeResolver $resolver;

    private readonly CampaignSaleRecorder $recorder;

    public function __construct(
        ?ReferralCodeResolver $resolver = null,
        ?CampaignSaleRecorder $recorder = null,
    ) {
        $this->resolver = $resolver ?? new ReferralCodeResolver;
        $this->recorder = $recorder ?? app(CampaignSaleRecorder::class);
    }

    /**
     * Record a referral click from a referral code.
     * Called when a customer visits the referral link so it can be matched later (checkout, classified inquiry,
     * trip booking). The click carries the campaign and marketer it belongs to and is remembered per vertical, so a
     * classified link never overwrites the product click of the same session (and vice versa).
     */
    public function recordClick(string $referralCode, string $sessionId, ?MarketerCampaignInvitation $invitation = null): void
    {
        $invitation ??= MarketerCampaignInvitation::where('referral_code', $referralCode)->with('campaign:id,campaign_category')->first();

        $payload = [
            'referral_code' => $referralCode,
            'clicked_at' => now()->toISOString(),
            'invitation_id' => $invitation?->id,
            'campaign_id' => $invitation?->campaign_id,
            'marketer_id' => $invitation?->marketer_id,
            'vertical' => self::verticalOf($invitation?->campaign?->campaign_category),
        ];
        $ttl = now()->addDays((int) setting('marketer_attribution_window_days', self::DEFAULT_ATTRIBUTION_WINDOW_DAYS));

        Cache::put("referral_click:{$sessionId}:{$payload['vertical']}", $payload, $ttl);
        // Legacy single slot, still read by older callers.
        Cache::put("referral_click:{$sessionId}", $payload, $ttl);
    }

    /** The remembered click of a session for one vertical (product | classified | travel), if any. */
    public function clickFor(?string $sessionId, string $vertical = 'product'): ?array
    {
        if (! $sessionId) {
            return null;
        }

        $click = Cache::get("referral_click:{$sessionId}:{$vertical}");
        if ($click) {
            return $click;
        }

        // Clicks recorded before per-vertical slots existed are product clicks.
        $legacy = Cache::get("referral_click:{$sessionId}");

        return $legacy && ($legacy['vertical'] ?? 'product') === $vertical ? $legacy : null;
    }

    public static function verticalOf(?string $campaignCategory): string
    {
        return in_array($campaignCategory, ['classified', 'travel'], true) ? $campaignCategory : 'product';
    }

    /**
     * On order placement: resolve attribution for every item of the order
     * (priority: marketer-listing cart item > last-click referral > none)
     * and create a MarketerCampaignConversion per attributed item.
     *
     * Called from CheckoutController after the order + order_items exist.
     */
    public function resolveAndRecordConversion(Order $order, ?string $sessionId): void
    {
        $order->loadMissing('items');

        $click = $this->clickFor($sessionId, 'product');
        $windowDays = (int) setting('marketer_attribution_window_days', self::DEFAULT_ATTRIBUTION_WINDOW_DAYS);

        // P14: the click resolves to exactly ONE invitation through ReferralCodeResolver: the live
        // (accepted) holder of the code, or - inside the grace window - the ORIGINAL holder when the
        // click was recorded before the ad moved on. Never both, never the replacement.
        $lastClickInvitation = null;
        $clickedAt = $click ? Carbon::parse($click['clicked_at']) : null;
        if ($click && now()->diffInDays($clickedAt) <= $windowDays) {
            $candidate = MarketerCampaignInvitation::where('referral_code', $click['referral_code'])
                ->with(['campaign', 'marketer.marketerProfile'])
                ->first();

            if ($candidate && $this->resolver->isAttributable($candidate, $clickedAt)) {
                $lastClickInvitation = $candidate;
            }
        }

        // One order => one marketer per campaign: the first attributed item fixes who is credited.
        $creditedByCampaign = [];

        // Direct purchases from a marketer's listing outrank a remembered click, so they fix the credited marketer first.
        foreach ($order->items->sortByDesc(fn ($i) => (bool) $i->marketer_listing_id)->values() as $item) {
            if ($item->marketerConversion()->exists()) {
                continue; // idempotency: retried place-order call.
            }

            $invitation = $this->resolveInvitationForItem($item, $lastClickInvitation);

            if (! $invitation) {
                continue;
            }

            if (isset($creditedByCampaign[$invitation->campaign_id]) && $creditedByCampaign[$invitation->campaign_id] !== $invitation->marketer_id) {
                continue; // never two marketers on one order for the same campaign
            }
            $creditedByCampaign[$invitation->campaign_id] = $invitation->marketer_id;

            $item->update(['marketer_campaign_invitation_id' => $invitation->id]);

            $this->recordConversion($invitation, $order, $item, $click);
        }
    }

    /**
     * Priority 1: marketer-listing cart item, IF that listing came from a
     * campaign invitation. Priority 2: the last-click invitation resolved
     * for the whole order (a referral click isn't scoped to one item).
     */
    private function resolveInvitationForItem(OrderItem $item, ?MarketerCampaignInvitation $lastClickInvitation): ?MarketerCampaignInvitation
    {
        if ($item->marketer_listing_id) {
            $item->loadMissing('marketerListing.invitation.campaign', 'marketerListing.invitation.marketer.marketerProfile');
            $invitation = $item->marketerListing?->invitation;

            if ($invitation && $this->resolver->stateFor($invitation) === ReferralCodeResolver::STATE_ACTIVE) {
                return $invitation;
            }

            // Independent marketer listing (no campaign) — attributed to the
            // marketer's sale for display purposes elsewhere, but there is
            // no campaign to earn a commission from, so no conversion.
            return null;
        }

        return $lastClickInvitation;
    }

    /**
     * Product vertical: the commission basis is the item price excluding VAT after discounts, for the whole line.
     * Everything else (influencer skip, budget cap, snapshots) is CampaignSaleRecorder's job.
     */
    private function recordConversion(MarketerCampaignInvitation $invitation, Order $order, OrderItem $item, ?array $click): void
    {
        $basis = max(0.0, round((float) $item->line_subtotal - (float) $item->line_discount, 2));

        $this->recorder->record(
            $invitation,
            ConversionSource::orderItem((string) $order->id, (string) $item->id),
            $basis,
            max(1, (int) $item->quantity),
            $item->commission_category_id,
            $order->created_at ?? now(),
            isset($click['clicked_at']) ? Carbon::parse($click['clicked_at']) : null,
        );
    }
}
