<?php

namespace App\Services;

use App\Models\Marketer;
use App\Models\MarketerCampaign;
use App\Models\MarketerCommissionCountrySetting;

/**
 * Per-sale (per sold unit) commission of a marketer on a campaign listing line, in this order:
 *   1. the marketer's own rule from the admin profile card "Commission Rates by Category"
 *      (MarketerCommissionRateService, ancestor-category fallback, then the profile's scope default);
 *   2. the affiliate default of the category edit page > Marketers tab for the campaign country
 *      (MarketerCommissionCountrySetting::forCategory, ancestor fallback), mode fixed|percentage|both;
 *   3. 0.
 * Influencers never use step 2 (D11): they earn the fee share instead, so only a profile rule gives them a
 * per-sale commission.
 */
class AffiliateCommissionResolver
{
    public const SOURCE_MARKETER_RULE = 'marketer_rule';

    public const SOURCE_CATEGORY_DEFAULT = 'category_default';

    public const SOURCE_NONE = 'none';

    public function __construct(private readonly MarketerCommissionRateService $rates) {}

    /** Commission for a line of $qty units worth $lineTotal. */
    public function forConversion(Marketer|string $marketer, MarketerCampaign $campaign, ?string $categoryId, int|float|string $lineTotal, int $qty = 1): float
    {
        return $this->resolve($marketer, $campaign->country_id, $categoryId, $lineTotal, $qty)['amount'];
    }

    /**
     * @return array{amount: float, source: string, mode: ?string, rate: ?float, flat: ?float}
     */
    public function resolve(Marketer|string $marketer, ?string $countryId, ?string $categoryId, int|float|string $lineTotal, int $qty = 1): array
    {
        $qty = max(1, $qty);

        $rule = $this->rates->resolveRule($marketer, $categoryId);
        if ($rule) {
            $amount = $rule->resolveAmount($lineTotal, $qty);
            if ($amount > 0) {
                return [
                    'amount' => $amount,
                    'source' => self::SOURCE_MARKETER_RULE,
                    'mode' => $rule->commission_mode,
                    'rate' => (float) $rule->commission_rate,
                    'flat' => $rule->commission_flat_amount !== null ? (float) $rule->commission_flat_amount : null,
                ];
            }
        }

        $isInfluencer = $marketer instanceof Marketer ? $marketer->isInfluencer() : Marketer::find($marketer)?->isInfluencer();
        if (! $isInfluencer) {
            $setting = MarketerCommissionCountrySetting::forCategory($countryId, $categoryId);
            $amount = $setting?->affiliateAmountFor($lineTotal, $qty) ?? 0.0;
            if ($setting && $amount > 0) {
                return [
                    'amount' => $amount,
                    'source' => self::SOURCE_CATEGORY_DEFAULT,
                    'mode' => $setting->affiliate_commission_mode,
                    'rate' => (float) $setting->affiliate_commission_rate,
                    'flat' => (float) $setting->affiliate_commission_flat,
                ];
            }
        }

        return ['amount' => 0.0, 'source' => self::SOURCE_NONE, 'mode' => null, 'rate' => null, 'flat' => null];
    }

    /** The category-tab affiliate default only (no marketer involved), used for the campaign-level default. */
    public function categoryDefault(?string $countryId, ?string $categoryId, int|float|string $lineTotal, int $qty = 1): float
    {
        return MarketerCommissionCountrySetting::forCategory($countryId, $categoryId)?->affiliateAmountFor($lineTotal, max(1, $qty)) ?? 0.0;
    }
}
