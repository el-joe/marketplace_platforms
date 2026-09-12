<?php

namespace App\Services;

use App\Models\FlashSale;
use App\Models\FlashSaleMarketerInvitation;
use App\Models\MarketerCampaignConversion;
use App\Models\MarketerCampaignInvitation;
use App\Models\Order;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class LastClickAttributionService
{
    public function __construct(
        private readonly MarketerCommissionRateService $commissionRates = new MarketerCommissionRateService(),
    ) {}

    /**
     * Record a referral click from a referral code.
     * Called when a customer visits the referral link so it can be matched at checkout.
     */
    public function recordClick(string $referralCode, string $sessionId): void
    {
        Cache::put("referral_click:{$sessionId}", [
            'referral_code' => $referralCode,
            'clicked_at'    => now()->toISOString(),
        ], now()->addDays(30));
    }

    /**
     * On order placement: resolve last-click attribution and create a conversion record.
     * Called from OrderService after the order is created.
     */
    public function resolveAndRecordConversion(Order $order, string $sessionId): void
    {
        $click = Cache::get("referral_click:{$sessionId}");
        if (!$click) {
            return;
        }

        $invitation = MarketerCampaignInvitation::where('referral_code', $click['referral_code'])
            ->where('status', 'accepted')
            ->with('campaign.tieredRules')
            ->first();

        if (!$invitation) {
            return;
        }
        if (!in_array($invitation->campaign->status, ['active', 'auto_approved'], true)) {
            return;
        }

        DB::transaction(function () use ($invitation, $order, $click, $sessionId) {
            $campaign          = $invitation->campaign;
            $saleNumber        = $invitation->total_conversions + 1;
            $commissionAmount  = 0;
            $applicableTierId  = null;

            switch ($campaign->commission_type) {
                case 'fixed':
                case 'last_click':
                    // Both use the marketer_commission_amount set by admin
                    $commissionAmount = $campaign->marketer_commission_amount;

                    // Fallback to category settings for auto-approved campaigns
                    if ($commissionAmount === 0) {
                        $setting = \App\Models\MarketerCommissionCountrySetting::where('country_id', $campaign->country_id)
                            ->whereHas('category', fn ($q) => $q->where('id',
                                $campaign->vendorListing?->productVariant?->product?->category_id
                                    ?? $campaign->adminListing?->productVariant?->product?->category_id
                            ))
                            ->first();

                        $marketer         = $invitation->marketer;
                        $commissionAmount = $setting
                            ? ($marketer->isInfluencer()
                                ? $setting->influencer_commission_amount
                                : $setting->affiliate_commission_amount)
                            : 0;
                    }

                    // Final fallback: marketer × category commission rate override
                    // (admin-configured per marketer, optionally per category).
                    if ($commissionAmount == 0) {
                        $categoryId = $campaign->vendorListing?->productVariant?->product?->category_id
                            ?? $campaign->adminListing?->productVariant?->product?->category_id;

                        $commissionAmount = $this->commissionRates->calculateCommissionAmount(
                            $invitation->marketer_id,
                            $categoryId,
                            (int) $order->total
                        );
                    }
                    break;

                case 'tiered':
                    $applicableTier = $campaign->tieredRules
                        ->where('from_sale_number', '<=', $saleNumber)
                        ->sortByDesc('from_sale_number')
                        ->first();
                    $commissionAmount = $applicableTier?->commission_amount ?? 0;
                    $applicableTierId = $applicableTier?->id;
                    break;
            }

            // Flash sale bonus: if this marketer has an accepted invitation to a
            // flash sale currently live, the order also earns a bonus commission
            // on top of the base campaign commission.
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
                    $order->total * ((float) $liveFlashSaleInvitation->extra_commission_rate / 100)
                );
            }

            $conversion = MarketerCampaignConversion::create([
                'campaign_id'             => $campaign->id,
                'invitation_id'           => $invitation->id,
                'order_id'                => $order->id,
                'referral_clicked_at'     => $click['clicked_at'],
                'commission_amount'       => $commissionAmount,
                'currency'                => $campaign->currency,
                'commissioned'            => false,
                'sale_number_in_campaign' => $saleNumber,
                'tiered_rule_id'          => $campaign->commission_type === 'tiered' ? $applicableTierId : null,
                'flash_sale_id'           => $flashSaleId,
                'flash_sale_bonus_amount' => $flashSaleBonusAmount,
            ]);

            $invitation->increment('total_conversions');
            $invitation->increment('total_commission_earned', $commissionAmount);

            $invitation->marketer->marketerAdmins->each(
                fn ($ma) => $ma->notify(new \App\Notifications\Marketer\NewConversionNotification($conversion, $ma->id))
            );

            // For last_click only: clear attribution after conversion.
            // For fixed/tiered: keep the cookie so repeat purchases also convert.
            if ($campaign->commission_type === 'last_click') {
                Cache::forget("referral_click:{$sessionId}");
            }
        });
    }
}
