<?php

namespace App\Services;

use App\Enums\MarketerCommissionDiscountType;
use App\Models\Country;
use App\Models\MarketerCampaignConversion;
use App\Models\MarketerProfile;

/**
 * Gross commission -> admin cut -> discount -> VAT -> net, in BIGINT base units.
 *
 * Order (fixed):
 *   1. cut_base = round_half_up(gross * cut% / 100)           (cut% = marketer_profiles.platform_cut_percentage)
 *   2. discount = the profile's admin-granted commission discount, which discounts THE CUT
 *                 (flat: min(flat, cut_base); percentage: floor(cut_base * pct / 100));
 *                 cut = cut_base - discount
 *   3. vat      = round_half_up(cut * countries.vat_rate / 100)   (VAT on the platform's service cut, as for P9 fees)
 *   4. net      = gross - cut - vat                              (credited to the wallet; withdrawals pay this)
 *
 * Percentages are converted to basis points (x100) so no float multiplication touches money.
 * Example: gross 100, 30% => cut 30, VAT 0 => net 70.
 */
class MarketerCommissionStatementService
{
    /**
     * @return array{gross: int, cut_percentage: float, cut_base: int, discount: int, cut: int, vat_rate: float, vat: int, net: int}
     */
    public function breakdown(int $gross, float|string $cutPct, ?MarketerProfile $profile = null, float|string $vatRate = 0): array
    {
        $gross = max(0, $gross);
        $cutBp = $this->bp($cutPct, 0, 10000);
        $cutBase = $this->mulBp($gross, $cutBp);

        $discount = 0;
        if ($profile && $cutBase > 0) {
            $type = $profile->commission_discount_type;
            if ($type === MarketerCommissionDiscountType::Flat) {
                $discount = min(max(0, (int) $profile->commission_discount_flat), $cutBase);
            } elseif ($type === MarketerCommissionDiscountType::Percentage) {
                $discount = intdiv($cutBase * $this->bp($profile->commission_discount_percentage, 0, 10000), 10000);
            }
        }

        $cut = $cutBase - $discount;
        $vatBp = $this->bp($vatRate, 0, 100000);
        $vat = $this->mulBp($cut, $vatBp);

        return [
            'gross' => $gross,
            'cut_percentage' => $cutBp / 100,
            'cut_base' => $cutBase,
            'discount' => $discount,
            'cut' => $cut,
            'vat_rate' => $vatBp / 100,
            'vat' => $vat,
            'net' => $gross - $cut - $vat,
        ];
    }

    public function vatRateForCountry(?string $countryId): float
    {
        return $countryId ? (float) (Country::whereKey($countryId)->value('vat_rate') ?? 0) : 0.0;
    }

    /** Live breakdown for a conversion that was not approved yet (preview). */
    public function previewFor(MarketerCampaignConversion $conversion): array
    {
        $profile = $conversion->invitation?->marketer?->marketerProfile;
        $countryId = $conversion->campaign?->country_id ?? $conversion->invitation?->marketer?->country_id;

        return $this->breakdown(
            $conversion->total_commission,
            $profile?->platform_cut_percentage ?? 0,
            $profile,
            $this->vatRateForCountry($countryId)
        );
    }

    /** Persisted breakdown when approved, otherwise a preview. */
    public function forConversion(MarketerCampaignConversion $c): array
    {
        if ($c->net_commission_amount !== null) {
            return [
                'gross' => (int) $c->gross_commission_amount,
                'cut_percentage' => (float) $c->platform_cut_percentage,
                'cut_base' => (int) $c->platform_cut_base_amount,
                'discount' => (int) $c->platform_cut_discount_amount,
                'cut' => (int) $c->platform_cut_amount,
                'vat_rate' => (float) $c->platform_cut_vat_rate,
                'vat' => (int) $c->platform_cut_vat_amount,
                'net' => (int) $c->net_commission_amount,
            ];
        }

        return $this->previewFor($c);
    }

    /** Snapshot attributes to store on the conversion at approval. */
    public function snapshotAttributes(MarketerCampaignConversion $c): array
    {
        $b = $this->previewFor($c);

        return [
            'gross_commission_amount' => $b['gross'],
            'platform_cut_percentage' => $b['cut_percentage'],
            'platform_cut_base_amount' => $b['cut_base'],
            'platform_cut_discount_amount' => $b['discount'],
            'platform_cut_amount' => $b['cut'],
            'platform_cut_vat_rate' => $b['vat_rate'],
            'platform_cut_vat_amount' => $b['vat'],
            'net_commission_amount' => $b['net'],
        ];
    }

    /** Amount the wallet holds for this conversion (net; legacy approved rows without snapshot = gross). */
    public static function walletAmount(MarketerCampaignConversion $c): int
    {
        return $c->net_commission_amount !== null ? (int) $c->net_commission_amount : $c->total_commission;
    }

    private function bp(float|string|int|null $pct, int $min, int $max): int
    {
        return max($min, min($max, (int) round(((float) $pct) * 100)));
    }

    private function mulBp(int $amount, int $bp): int
    {
        return intdiv($amount * $bp + 5000, 10000);
    }
}
