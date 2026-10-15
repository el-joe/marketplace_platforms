<?php

namespace App\Services\Marketer;

use App\Enums\CommissionVertical;
use App\Models\Marketer;
use App\Models\MarketerCampaignConversion;
use App\Models\MarketerCampaignInvitation;
use App\Support\Money;

/**
 * Read model of what a marketer earns on a conversion: gross commission, the platform's commission fee, net.
 *
 * `breakdown()` keeps the array keys the marketer / admin statement screens already read (cut = the commission fee,
 * discount = vat = 0 since the profile discount and VAT-on-cut no longer exist) and adds `fee`, so those screens keep
 * working while phase F redesigns them.
 */
class MarketerEarningsService
{
    public function __construct(private readonly CommissionFeeService $fees) {}

    /**
     * @return array{gross: float, fee: float, net: float, cut: float, cut_percentage: float, cut_base: float, discount: float, vat_rate: float, vat: float}
     */
    public function breakdown(MarketerCampaignConversion $c): array
    {
        if ($c->commission_fee_amount !== null) {
            $gross = round((float) ($c->gross_commission_amount ?? $c->commission_amount), 2);
            $fee = round((float) $c->commission_fee_amount, 2);

            return $this->shape($gross, $fee, round((float) ($c->net_commission_amount ?? $gross - $fee), 2), 0.0);
        }

        if ($c->net_commission_amount !== null) {
            // Approved before v3: the stored cut + VAT snapshot is the truth, never recomputed.
            $gross = round((float) $c->gross_commission_amount, 2);
            $cut = round((float) $c->platform_cut_amount, 2);
            $vat = round((float) $c->platform_cut_vat_amount, 2);

            return $this->shape($gross, $cut, round((float) $c->net_commission_amount, 2), $vat, (float) $c->platform_cut_percentage);
        }

        return $this->preview($c);
    }

    /** The fee a conversion without a snapshot would be charged under the current settings (pending legacy rows). */
    public function preview(MarketerCampaignConversion $c): array
    {
        $gross = round((float) $c->commission_amount, 2);
        $marketer = $c->invitation?->marketer;
        $campaign = $c->campaign;
        if (! $marketer || ! $campaign || $gross <= 0) {
            return $this->shape($gross, 0.0, $gross, 0.0);
        }

        $vertical = CommissionVertical::tryFrom((string) $campaign->campaign_category) ?? CommissionVertical::Product;
        [$pct, $fixed] = $this->fees->resolve($marketer, $vertical, null, $campaign->country_id);
        $split = CommissionFeeService::split($gross, $pct, $fixed);

        return $this->shape($split['gross'], $split['fee'], $split['net'], 0.0);
    }

    /**
     * Fee and net snapshot columns to store on a conversion that has none yet (legacy pending rows at approval).
     *
     * @return array<string, float>
     */
    public function snapshotAttributes(MarketerCampaignConversion $c): array
    {
        $b = $this->preview($c);

        return [
            'gross_commission_amount' => $b['gross'],
            'commission_fee_amount' => $b['fee'],
            'net_commission_amount' => $b['net'],
        ];
    }

    /** Amount the marketer wallet holds for this conversion (net; rows without a snapshot = gross). */
    public function walletAmount(MarketerCampaignConversion $c): float
    {
        return $c->net_commission_amount !== null ? (float) $c->net_commission_amount : (float) $c->commission_amount;
    }

    /** Human line for the wallet transaction written when the conversion's net becomes spendable. */
    public function walletDescription(MarketerCampaignConversion $c): string
    {
        $b = $this->breakdown($c);
        $reference = $c->order?->order_number
            ?? '#'.substr((string) ($c->order_id ?? $c->classified_listing_id ?? $c->travel_booking_id ?? $c->id), 0, 8);

        return mb_substr(__('marketer.finance.tx_commission', [
            'order' => $reference, 'gross' => Money::format($b['gross']), 'cut' => Money::format($b['cut']),
            'vat' => Money::format($b['vat']), 'net' => Money::format($b['net']),
        ]), 0, 255);
    }

    /**
     * Lifetime statement of one marketer over conversions in the given statuses (approved by default).
     *
     * @return array{gross: float, cut: float, vat: float, net: float, count: int}
     */
    public function totalsForMarketer(Marketer|string $marketer, array $statuses = ['approved']): array
    {
        $marketerId = $marketer instanceof Marketer ? $marketer->id : $marketer;
        $totals = ['gross' => 0.0, 'cut' => 0.0, 'vat' => 0.0, 'net' => 0.0, 'count' => 0];

        MarketerCampaignConversion::query()
            ->whereIn('status', $statuses)
            ->whereIn('invitation_id', MarketerCampaignInvitation::where('marketer_id', $marketerId)->select('id'))
            ->with(['campaign', 'invitation.marketer'])
            ->each(function (MarketerCampaignConversion $c) use (&$totals) {
                $b = $this->breakdown($c);
                foreach (['gross', 'cut', 'vat', 'net'] as $k) {
                    $totals[$k] = round($totals[$k] + $b[$k], 2);
                }
                $totals['count']++;
            });

        return $totals;
    }

    /**
     * @return array{gross: float, fee: float, net: float, cut: float, cut_percentage: float, cut_base: float, discount: float, vat_rate: float, vat: float}
     */
    private function shape(float $gross, float $fee, float $net, float $vat, ?float $cutPercentage = null): array
    {
        return [
            'gross' => $gross,
            'fee' => $fee,
            'net' => $net,
            'cut' => $fee,
            'cut_percentage' => $cutPercentage ?? ($gross > 0 ? round($fee / $gross * 100, 2) : 0.0),
            'cut_base' => $fee,
            'discount' => 0.0,
            'vat_rate' => 0.0,
            'vat' => $vat,
        ];
    }
}
