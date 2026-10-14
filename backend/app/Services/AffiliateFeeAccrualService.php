<?php

namespace App\Services;

use App\Enums\MarketingFeeType;
use App\Models\MarketerCampaign;
use App\Models\MarketerCampaignConversion;
use App\Models\MarketerCampaignFeeAccrual;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Affiliate marketing-fee accrual ledger (plan 4.4 / D4 / D6). One row per APPROVED conversion of an affiliate on a
 * vendor campaign: the marketing fees on that conversion's commission, with running totals. Flat amounts are accrued
 * once (the campaign's first accrual with a positive base, tracked by marketing_fee_fixed_accrued); percentage parts
 * accrue on every base. A refund/reversal writes a negative `reversal` row (percentage parts only: flat stays).
 * Debiting the vendor is AffiliateFeeSettlementService's job.
 */
class AffiliateFeeAccrualService
{
    public function __construct(private readonly MarketingFeeService $fees = new MarketingFeeService) {}

    /** Only vendor campaigns pay marketing fees, and only conversions of affiliates (never influencers, D11). */
    public function appliesTo(MarketerCampaignConversion $conversion): bool
    {
        $campaign = $conversion->campaign;
        $marketer = $conversion->invitation?->marketer;

        return $campaign !== null
            && $campaign->vendor_id !== null
            && $marketer !== null
            && $marketer->isAffiliate()
            && ! $marketer->isInfluencer();
    }

    /** Idempotent on (conversion, accrual). Returns the row, or null when nothing applies. */
    public function accrue(MarketerCampaignConversion $conversion): ?MarketerCampaignFeeAccrual
    {
        $conversion->loadMissing(['campaign', 'invitation.marketer', 'orderItem']);
        if ($conversion->status !== 'approved' || ! $this->appliesTo($conversion)) {
            return null;
        }

        $base = round((float) $conversion->commission_amount, 2);
        if ($base <= 0) {
            return null;
        }

        return DB::transaction(function () use ($conversion, $base) {
            $campaign = MarketerCampaign::whereKey($conversion->campaign_id)->lockForUpdate()->firstOrFail();

            $existing = MarketerCampaignFeeAccrual::where('conversion_id', $conversion->id)
                ->where('entry_type', MarketerCampaignFeeAccrual::ENTRY_ACCRUAL)->first();
            if ($existing) {
                return $existing;
            }

            $type = $this->feeType($campaign);
            $categoryId = $conversion->orderItem?->commission_category_id ?? $this->listingCategoryId($campaign);
            $firstWithFlat = (float) $campaign->marketing_fee_fixed_accrued <= 0;

            $calc = $this->fees->calculate($base, $campaign->country_id, $categoryId, $type, $firstWithFlat);
            $flat = $firstWithFlat
                ? round($calc['total_fee'] - $this->fees->calculate($base, $campaign->country_id, $categoryId, $type, false)['total_fee'], 2)
                : 0.0;

            $row = $this->record($campaign, $conversion, MarketerCampaignFeeAccrual::ENTRY_ACCRUAL, $base, $calc['fixed_fee'], $calc['variable_fee'], $calc['total_fee'], MarketerCampaignFeeAccrual::STATUS_ACCRUED);

            if ($flat > 0) {
                $campaign->update(['marketing_fee_fixed_accrued' => round((float) $campaign->marketing_fee_fixed_accrued + $flat, 4)]);
            }

            return $row;
        });
    }

    /**
     * Refund / reversal of an approved conversion: a negative row cancelling the percentage parts of its accrual.
     * Flat amounts stay accrued. Idempotent; no-op when the conversion never accrued.
     */
    public function reverse(MarketerCampaignConversion $conversion): ?MarketerCampaignFeeAccrual
    {
        $original = MarketerCampaignFeeAccrual::where('conversion_id', $conversion->id)
            ->where('entry_type', MarketerCampaignFeeAccrual::ENTRY_ACCRUAL)->first();
        if (! $original) {
            return null;
        }

        return DB::transaction(function () use ($conversion, $original) {
            $campaign = MarketerCampaign::whereKey($original->campaign_id)->lockForUpdate()->firstOrFail();

            $existing = MarketerCampaignFeeAccrual::where('conversion_id', $conversion->id)
                ->where('entry_type', MarketerCampaignFeeAccrual::ENTRY_REVERSAL)->first();
            if ($existing) {
                return $existing;
            }

            $base = round((float) $original->commission_base, 2);
            $calc = $this->fees->calculate($base, $campaign->country_id, $conversion->orderItem?->commission_category_id ?? $this->listingCategoryId($campaign), $this->feeType($campaign), false);

            // Never give back more than the original accrual charged.
            $total = min($calc['total_fee'], round((float) $original->marketing_total_delta, 2));
            $fixed = min($calc['fixed_fee'], $total);
            $variable = round($total - $fixed, 2);

            return $this->record($campaign, $conversion, MarketerCampaignFeeAccrual::ENTRY_REVERSAL, -$base, -$fixed, -$variable, -$total, MarketerCampaignFeeAccrual::STATUS_REVERSED, $original);
        });
    }

    private function record(
        MarketerCampaign $campaign,
        MarketerCampaignConversion $conversion,
        string $entryType,
        float $base,
        float $fixed,
        float $variable,
        float $total,
        string $status,
        ?MarketerCampaignFeeAccrual $original = null,
    ): MarketerCampaignFeeAccrual {
        $item = $conversion->orderItem;
        $sign = $entryType === MarketerCampaignFeeAccrual::ENTRY_REVERSAL ? -1 : 1;

        $runningBase = round((float) MarketerCampaignFeeAccrual::where('campaign_id', $campaign->id)->sum('commission_base') + $base, 2);
        $runningTotal = round((float) MarketerCampaignFeeAccrual::where('campaign_id', $campaign->id)->sum('marketing_total_delta') + $total, 2);

        $row = MarketerCampaignFeeAccrual::create([
            'campaign_id' => $campaign->id,
            'invitation_id' => $conversion->invitation_id,
            'conversion_id' => $conversion->id,
            'entry_type' => $entryType,
            'order_id' => $conversion->order_id,
            'order_item_id' => $conversion->order_item_id,
            'quantity' => (int) ($original?->quantity ?? $item?->quantity ?? 0),
            'line_total' => $sign * (float) ($original?->line_total ?? $item?->line_total ?? 0),
            'commission_base' => $base,
            'marketing_fixed_delta' => $fixed,
            'marketing_variable_delta' => $variable,
            'marketing_total_delta' => $total,
            'running_base' => $runningBase,
            'running_marketing_total' => $runningTotal,
            'status' => $status,
        ]);

        $campaign->update(['affiliate_fee_accrued' => $runningTotal]);

        if ($entryType === MarketerCampaignFeeAccrual::ENTRY_REVERSAL) {
            Log::info('Affiliate marketing fee reversed.', ['campaign_id' => $campaign->id, 'conversion_id' => $conversion->id, 'delta' => $total]);
        }

        return $row;
    }

    /** Campaign snapshot, else derived from the promoted listing (vendor listing model / admin listing type). */
    private function feeType(MarketerCampaign $campaign): MarketingFeeType
    {
        if ($campaign->fulfillment_type instanceof MarketingFeeType) {
            return $campaign->fulfillment_type;
        }

        $model = $campaign->vendorListing?->fulfillment_model ?? $campaign->adminListing?->fulfillment_type;

        return MarketingFeeType::fromListingModel($model);
    }

    private function listingCategoryId(MarketerCampaign $campaign): ?string
    {
        return $campaign->vendorListing?->productVariant?->product?->category_id
            ?? $campaign->adminListing?->productVariant?->product?->category_id;
    }
}
