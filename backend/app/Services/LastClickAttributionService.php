<?php

namespace App\Services;

use App\Models\MarketerCampaignConversion;
use App\Models\MarketerCampaignInvitation;
use App\Models\Order;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class LastClickAttributionService
{
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

                        $marketerVendor   = $invitation->marketer;
                        $commissionAmount = $setting
                            ? ($marketerVendor->marketer_type === 'influencer'
                                ? $setting->influencer_commission_amount
                                : $setting->affiliate_commission_amount)
                            : 0;
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

            MarketerCampaignConversion::create([
                'campaign_id'             => $campaign->id,
                'invitation_id'           => $invitation->id,
                'order_id'                => $order->id,
                'referral_clicked_at'     => $click['clicked_at'],
                'commission_amount'       => $commissionAmount,
                'currency'                => $campaign->currency,
                'commissioned'            => false,
                'sale_number_in_campaign' => $saleNumber,
                'tiered_rule_id'          => $campaign->commission_type === 'tiered' ? $applicableTierId : null,
            ]);

            $invitation->increment('total_conversions');
            $invitation->increment('total_commission_earned', $commissionAmount);

            // For last_click only: clear attribution after conversion.
            // For fixed/tiered: keep the cookie so repeat purchases also convert.
            if ($campaign->commission_type === 'last_click') {
                Cache::forget("referral_click:{$sessionId}");
            }
        });
    }
}
