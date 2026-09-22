<?php

namespace App\Services;

use App\Models\FlashSaleMarketerInvitation;
use App\Models\MarketerCampaign;
use App\Models\MarketerCampaignConversion;
use App\Models\MarketerCampaignInvitation;
use App\Models\MarketerCommissionCountrySetting;
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

    public function __construct(
        private readonly MarketerCommissionRateService $commissionRates = new MarketerCommissionRateService,
    ) {}

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

        $lastClickInvitation = null;
        if ($click && now()->diffInDays(Carbon::parse($click['clicked_at'])) <= $windowDays) {
            $lastClickInvitation = MarketerCampaignInvitation::where('referral_code', $click['referral_code'])
                ->where('status', 'accepted')
                ->with(['campaign.tieredRules', 'marketer.marketerProfile'])
                ->first();

            if ($lastClickInvitation && ! in_array($lastClickInvitation->campaign->status, ['active', 'auto_approved'], true)) {
                $lastClickInvitation = null;
            }
        }

        foreach ($order->items as $item) {
            if ($item->marketerConversion()->exists()) {
                continue; // idempotency: retried place-order call.
            }

            $invitation = $this->resolveInvitationForItem($item, $lastClickInvitation);

            if (! $invitation) {
                continue;
            }

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

            if ($invitation && in_array($invitation->campaign->status, ['active', 'auto_approved'], true)) {
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
        DB::transaction(function () use ($invitation, $order, $item, $click) {
            // Row-lock the campaign for the whole compute+spend+pause
            // sequence (enhancement.md P-12 task 2 — same reserve pattern
            // as P-04's CouponUsageService::reserve()).
            /** @var MarketerCampaign $campaign */
            $campaign = MarketerCampaign::where('id', $invitation->campaign_id)->lockForUpdate()->firstOrFail();
            $invitation->refresh();

            $saleNumber = $invitation->total_conversions + 1;
            $commissionAmount = 0;
            $applicableTierId = null;

            switch ($campaign->commission_type) {
                case 'tiered':
                    $tiers = $campaign->tieredRules;
                    $applicableTier = $tiers->where('from_sale_number', '<=', $saleNumber)
                        ->sortByDesc('from_sale_number')
                        ->first();
                    $commissionAmount = (int) ($applicableTier?->commission_amount ?? 0);
                    $applicableTierId = $applicableTier?->id;
                    break;

                case 'fixed':
                case 'last_click':
                default:
                    $commissionAmount = (int) ($campaign->marketer_commission_amount * $item->quantity);

                    if ($commissionAmount <= 0) {
                        $categoryId = $item->commission_category_id;
                        $setting = MarketerCommissionCountrySetting::where('country_id', $campaign->country_id)
                            ->where('category_id', $categoryId)
                            ->first();

                        $marketer = $invitation->marketer;
                        if ($setting) {
                            $perUnit = $marketer->isInfluencer()
                                ? $setting->influencer_commission_amount
                                : $setting->affiliate_commission_amount;
                            $commissionAmount = (int) $perUnit * $item->quantity;
                        }
                    }

                    if ($commissionAmount <= 0) {
                        $commissionAmount = $this->commissionRates->calculateCommissionAmount(
                            $invitation->marketer_id,
                            $item->commission_category_id,
                            $item->line_total
                        );
                    }
                    break;
            }

            $marketerProfile = $invitation->marketer->marketerProfile;
            if ($marketerProfile) {
                $commissionAmount = (int) $marketerProfile->applyCommissionDiscount($commissionAmount);
            }

            // Flash sale bonus, if this marketer has an accepted invitation to
            // a currently-live flash sale.
            $flashSaleId = null;
            $flashSaleBonusAmount = null;
            $liveFlashSaleInvitation = FlashSaleMarketerInvitation::where('marketer_id', $invitation->marketer_id)
                ->where('status', 'accepted')
                ->whereHas('flashSale', fn ($q) => $q->where('status', 'live'))
                ->with('flashSale')
                ->first();

            if ($liveFlashSaleInvitation && $liveFlashSaleInvitation->extra_commission_rate) {
                $flashSaleId = $liveFlashSaleInvitation->flash_sale_id;
                $flashSaleBonusAmount = (int) round(
                    $item->line_total * ((float) $liveFlashSaleInvitation->extra_commission_rate / 100)
                );
            }

            $totalCommission = $commissionAmount + (int) ($flashSaleBonusAmount ?? 0);

            $conversion = MarketerCampaignConversion::create([
                'campaign_id' => $campaign->id,
                'invitation_id' => $invitation->id,
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'referral_clicked_at' => $click['clicked_at'] ?? null,
                'commission_amount' => $commissionAmount,
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
    }
}
