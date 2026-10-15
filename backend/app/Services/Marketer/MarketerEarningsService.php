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
 *   gross = what the campaign budget pays for the sale / booking / lead
 *   fee   = the platform commission fee, taken out of the gross
 *   net   = gross - fee = what lands in the marketer wallet
 *
 * Conversions approved before v3 carry no fee snapshot of their own: the data migration folded their old platform cut
 * (and VAT) into `commission_fee_amount`, so every row reads the same way.
 */
class MarketerEarningsService
{
    public function __construct(private readonly CommissionFeeService $fees) {}

    /**
     * @return array{gross: float, fee: float, net: float, fee_percentage: float}
     */
    public function breakdown(MarketerCampaignConversion $c): array
    {
        if ($c->commission_fee_amount !== null) {
            $gross = round((float) ($c->gross_commission_amount ?? $c->commission_amount), 2);
            $fee = round((float) $c->commission_fee_amount, 2);

            return $this->shape($gross, $fee, round((float) ($c->net_commission_amount ?? $gross - $fee), 2));
        }

        return $this->preview($c);
    }

    /** The fee a conversion without a snapshot would be charged under the current settings (pending rows). */
    public function preview(MarketerCampaignConversion $c): array
    {
        $gross = round((float) $c->commission_amount, 2);
        $marketer = $c->invitation?->marketer;
        $campaign = $c->campaign;
        if (! $marketer || ! $campaign || $gross <= 0) {
            return $this->shape($gross, 0.0, $gross);
        }

        $vertical = CommissionVertical::tryFrom((string) $campaign->campaign_category) ?? CommissionVertical::Product;
        [$pct, $fixed] = $this->fees->resolve($marketer, $vertical, null, $campaign->country_id);
        $split = CommissionFeeService::split($gross, $pct, $fixed);

        return $this->shape($split['gross'], $split['fee'], $split['net']);
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
            'order' => $reference, 'gross' => Money::format($b['gross']), 'fee' => Money::format($b['fee']),
            'net' => Money::format($b['net']),
        ]), 0, 255);
    }

    /**
     * Lifetime statement of one marketer over conversions in the given statuses (approved by default).
     *
     * @return array{gross: float, fee: float, net: float, count: int}
     */
    public function totalsForMarketer(Marketer|string $marketer, array $statuses = ['approved']): array
    {
        $marketerId = $marketer instanceof Marketer ? $marketer->id : $marketer;
        $totals = ['gross' => 0.0, 'fee' => 0.0, 'net' => 0.0, 'count' => 0];

        MarketerCampaignConversion::query()
            ->whereIn('status', $statuses)
            ->whereIn('invitation_id', MarketerCampaignInvitation::where('marketer_id', $marketerId)->select('id'))
            ->with(['campaign', 'invitation.marketer'])
            ->each(function (MarketerCampaignConversion $c) use (&$totals) {
                $b = $this->breakdown($c);
                foreach (['gross', 'fee', 'net'] as $k) {
                    $totals[$k] = round($totals[$k] + $b[$k], 2);
                }
                $totals['count']++;
            });

        return $totals;
    }

    /**
     * Per-campaign fixed amount lines of an influencer (reserved / paid / released), gross - fee = net.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function fixedAmountLines(Marketer|string $marketer): \Illuminate\Support\Collection
    {
        $marketerId = $marketer instanceof Marketer ? $marketer->id : $marketer;

        return MarketerCampaignInvitation::query()
            ->where('marketer_id', $marketerId)
            ->whereNotNull('influencer_fixed_status')
            ->where('influencer_fixed_status', '!=', MarketerCampaignInvitation::FIXED_NONE)
            ->with(['campaign' => fn ($q) => $q->withTrashed()->select('id', 'title', 'currency', 'status', 'campaign_category')])
            ->latest('updated_at')
            ->get()
            ->map(function (MarketerCampaignInvitation $i) {
                $status = (string) $i->influencer_fixed_status;
                $gross = round((float) $i->influencer_fixed_amount, 2);
                $fee = $i->influencer_fixed_fee_amount !== null ? round((float) $i->influencer_fixed_fee_amount, 2) : null;
                $net = $i->influencer_fixed_net_amount !== null ? round((float) $i->influencer_fixed_net_amount, 2) : null;

                return [
                    'invitation_id' => $i->id,
                    'campaign_id' => $i->campaign_id,
                    'campaign_title' => $i->campaign?->title ?: '#'.substr((string) $i->campaign_id, 0, 8),
                    'currency' => (string) ($i->campaign?->currency ?? ''),
                    'status' => $status,
                    'gross' => $gross,
                    // The fee is only known once the amount is paid; until then show the gross that is held for him.
                    'fee' => $status === MarketerCampaignInvitation::FIXED_PAID ? ($fee ?? 0.0) : null,
                    'net' => $status === MarketerCampaignInvitation::FIXED_PAID ? ($net ?? $gross) : null,
                    'at' => match ($status) {
                        MarketerCampaignInvitation::FIXED_PAID => $i->influencer_fixed_paid_at,
                        MarketerCampaignInvitation::FIXED_RELEASED => $i->influencer_fixed_released_at,
                        default => $i->influencer_fixed_reserved_at,
                    },
                ];
            });
    }

    /**
     * @return array{gross: float, fee: float, net: float, fee_percentage: float}
     */
    private function shape(float $gross, float $fee, float $net): array
    {
        return [
            'gross' => $gross,
            'fee' => $fee,
            'net' => $net,
            'fee_percentage' => $gross > 0 ? round($fee / $gross * 100, 2) : 0.0,
        ];
    }
}
