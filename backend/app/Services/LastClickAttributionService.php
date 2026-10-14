<?php

namespace App\Services;

use App\Models\FlashSaleMarketerInvitation;
use App\Models\MarketerCampaign;
use App\Models\MarketerCampaignConversion;
use App\Models\MarketerCampaignInvitation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Notifications\Marketer\NewConversionNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

    public function __construct(
        private readonly MarketerCommissionRateService $commissionRates = new MarketerCommissionRateService,
        ?ReferralCodeResolver $resolver = null,
    ) {
        $this->resolver = $resolver ?? new ReferralCodeResolver;
    }

    /**
     * Record a referral click from a referral code.
     * Called when a customer visits the referral link so it can be matched at checkout.
     */
    public function recordClick(string $referralCode, string $sessionId): void
    {
        Cache::put("referral_click:{$sessionId}", [
            'referral_code' => $referralCode,
            'clicked_at' => now()->toISOString(),
        ], now()->addDays((int) setting('marketer_attribution_window_days', self::DEFAULT_ATTRIBUTION_WINDOW_DAYS)));
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

        $click = $sessionId ? Cache::get("referral_click:{$sessionId}") : null;
        $windowDays = (int) setting('marketer_attribution_window_days', self::DEFAULT_ATTRIBUTION_WINDOW_DAYS);

        // P14: the click resolves to exactly ONE invitation through ReferralCodeResolver: the live
        // (accepted) holder of the code, or - inside the grace window - the ORIGINAL holder when the
        // click was recorded before the ad moved on. Never both, never the replacement.
        $lastClickInvitation = null;
        $clickedAt = $click ? Carbon::parse($click['clicked_at']) : null;
        if ($click && now()->diffInDays($clickedAt) <= $windowDays) {
            $candidate = MarketerCampaignInvitation::where('referral_code', $click['referral_code'])
                ->with(['campaign.tieredRules', 'marketer.marketerProfile'])
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
            $item->loadMissing('marketerListing.invitation.campaign.tieredRules', 'marketerListing.invitation.marketer.marketerProfile');
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

    private function recordConversion(MarketerCampaignInvitation $invitation, Order $order, OrderItem $item, ?array $click): void
    {
        $pausedForBudget = false;

        DB::transaction(function () use ($invitation, $order, $item, $click, &$pausedForBudget) {
            // Row-lock the campaign for the whole compute+spend+pause
            // sequence (enhancement.md P-12 task 2 — same reserve pattern
            // as P-04's CouponUsageService::reserve()).
            /** @var MarketerCampaign $campaign */
            $campaign = MarketerCampaign::where('id', $invitation->campaign_id)->lockForUpdate()->firstOrFail();
            $invitation->refresh();

            // Double-credit guard (retried request / concurrent resolution): one conversion per order item, ever.
            if (MarketerCampaignConversion::where('order_item_id', $item->id)->exists()) {
                return;
            }

            $saleNumber = $invitation->total_conversions + 1;
            $commissionAmount = 0.0;
            $applicableTierId = null;

            switch ($campaign->commission_type) {
                case 'tiered':
                    $tiers = $campaign->tieredRules;
                    $applicableTier = $tiers->where('from_sale_number', '<=', $saleNumber)
                        ->sortByDesc('from_sale_number')
                        ->first();
                    $commissionAmount = (float) ($applicableTier?->commission_amount ?? 0);
                    $applicableTierId = $applicableTier?->id;
                    break;

                case 'fixed':
                case 'last_click':
                default:
                    $categoryId = $item->commission_category_id;
                    $marketer = $invitation->marketer;
                    $resolver = new AffiliateCommissionResolver($this->commissionRates);

                    // A per-unit amount typed on the campaign (admin approval, peer promotion) wins. The affiliate
                    // default stored at approval is not a typed override: the marketer's own rule, else the
                    // category default (affiliates only, D11) is resolved on the real line instead.
                    $campaignPerUnit = (float) $campaign->marketer_commission_amount;
                    if ($campaignPerUnit > 0 && ! $this->isStoredCategoryDefault($campaign, $categoryId, $item, $resolver)) {
                        $commissionAmount = round($campaignPerUnit * $item->quantity, 2);
                    } else {
                        $commissionAmount = $resolver->forConversion($marketer, $campaign, $categoryId, $item->line_total, (int) $item->quantity);
                    }
                    break;
            }

            // P11: the profile's commission discount no longer reduces the gross; it discounts the
            // admin cut at approval (MarketerCommissionStatementService).

            // Flash sale bonus, if this marketer has an accepted invitation to
            // a currently-live flash sale.
            $flashSaleId = null;
            $flashSaleBonusAmount = null;
            $liveFlashSaleInvitation = FlashSaleMarketerInvitation::where('marketer_id', $invitation->marketer_id)
                ->where('status', 'accepted')
                ->whereHas('flashSale', fn ($q) => $q->where('status', 'live'))
                ->with('flashSale')
                ->first();

            if ($liveFlashSaleInvitation && $liveFlashSaleInvitation->hasExtraCommission()) {
                $flashSaleId = $liveFlashSaleInvitation->flash_sale_id;
                $flashSaleBonusAmount = $liveFlashSaleInvitation->calculateBonus(
                    (float) $item->line_total,
                    (int) $item->quantity
                );
            }

            $totalCommission = round($commissionAmount + (float) ($flashSaleBonusAmount ?? 0), 2);

            $conversion = MarketerCampaignConversion::create([
                'campaign_id' => $campaign->id,
                'invitation_id' => $invitation->id,
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'referral_clicked_at' => $click['clicked_at'] ?? null,
                'commission_amount' => $commissionAmount,
                'platform_commission_amount' => round((float) $campaign->platform_commission_amount * $item->quantity, 2),
                'currency' => $campaign->currency,
                'status' => 'pending',
                'commissioned' => false,
                'sale_number_in_campaign' => $saleNumber,
                'tiered_rule_id' => $campaign->commission_type === 'tiered' ? $applicableTierId : null,
                'flash_sale_id' => $flashSaleId,
                'flash_sale_bonus_amount' => $flashSaleBonusAmount,
            ]);

            $invitation->increment('total_conversions');
            $invitation->increment('total_commission_earned', $commissionAmount);

            // enhancement.md P-12 task 2: enforce max_commission_budget —
            // the campaign row is already locked above, so this increment
            // and the pause decision below serialize against concurrent
            // orders on the same campaign.
            $campaign->increment('commission_budget_spent', $totalCommission);
            $campaign->refresh();

            if (
                $campaign->max_commission_budget > 0
                && $campaign->commission_budget_spent >= $campaign->max_commission_budget
                && in_array($campaign->status, ['active', 'auto_approved'], true)
            ) {
                $campaign->update(['status' => 'paused']);
                $pausedForBudget = true;
                Log::info('MarketerCampaign auto-paused: commission budget exhausted.', [
                    'campaign_id' => $campaign->id,
                    'spent' => $campaign->commission_budget_spent,
                    'budget' => $campaign->max_commission_budget,
                ]);
            }

            $invitation->marketer->marketerAdmins->each(
                fn ($ma) => $ma->notify(new NewConversionNotification($conversion, $ma->id))
            );
        });

        // P6: budget exhausted -> settle the affiliate marketing fees accrued so far (later approvals settle on arrival).
        if ($pausedForBudget) {
            app(AffiliateFeeSettlementService::class)->settleIfClosed($invitation->campaign_id, 'budget_exhausted');
        }
    }

    /** True when the campaign's per-unit amount is just the category affiliate default stored at approval. */
    private function isStoredCategoryDefault(MarketerCampaign $campaign, ?string $categoryId, OrderItem $item, AffiliateCommissionResolver $resolver): bool
    {
        $stored = (float) $campaign->marketer_commission_amount;
        $prices = array_filter([
            $campaign->vendorListing?->price,
            $campaign->adminListing?->price,
            $item->quantity > 0 ? (float) $item->line_total / (int) $item->quantity : null,
        ], fn ($price) => $price !== null);

        foreach ($prices as $price) {
            $default = $resolver->categoryDefault($campaign->country_id, $categoryId, $price, 1);
            if ($default > 0 && abs($default - $stored) < 0.005) {
                return true;
            }
        }

        return false;
    }
}
