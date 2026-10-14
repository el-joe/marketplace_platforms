<?php

namespace App\Services;

use App\Models\MarketerCommissionCountrySetting;

/**
 * Influencer fee-share % (Category edit > Marketers tab, per country, ancestor fallback) and the payout it
 * implies: marketer_payout = influencer_fee x share% / 100; the platform keeps the rest.
 */
class MarketerFeeShareResolver
{
    /** Share % for the category (nearest ancestor with a setting), 0 when none. */
    public function sharePct(?string $countryId, ?string $categoryId): float
    {
        $setting = MarketerCommissionCountrySetting::forCategory($countryId, $categoryId);

        return $setting ? (float) $setting->influencer_fee_share_pct : 0.0;
    }

    /** @return array{share_pct: float, marketer_payout: float, platform_keeps: float} */
    public function split(int|float|string|null $influencerFee, ?string $countryId, ?string $categoryId): array
    {
        return $this->splitWithPct($influencerFee, $this->sharePct($countryId, $categoryId));
    }

    /** @return array{share_pct: float, marketer_payout: float, platform_keeps: float} */
    public function splitWithPct(int|float|string|null $influencerFee, float $sharePct): array
    {
        $fee = (float) $influencerFee;
        $payout = $fee > 0 && $sharePct > 0 ? round($fee * $sharePct / 100, 2) : 0.0;

        return [
            'share_pct' => $sharePct,
            'marketer_payout' => $payout,
            'platform_keeps' => round($fee - $payout, 2),
        ];
    }
}
