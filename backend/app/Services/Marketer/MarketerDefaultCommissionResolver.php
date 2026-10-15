<?php

namespace App\Services\Marketer;

use App\Enums\CommissionVertical;
use App\Models\MarketerDefaultCommission;

/**
 * Platform defaults configured on the admin "Marketer Settings" page (marketer_default_commissions).
 *
 * Lookup order for a country + vertical (+ optional category):
 *   1. the category-specific row, when one exists;
 *   2. the vertical-wide row (category_id NULL);
 *   3. nothing configured => 0.
 *
 * - influencer fixed amount: the ONE amount an influencer earns per campaign. A category row that exists wins,
 *   even when it is 0 (an explicit "free" override).
 * - commission fee (pct + fixed): taken from every marketer commission. Category rows are only created from the
 *   influencer-amount grid, so a category row with both fee columns at 0 does not shadow the vertical-wide fee.
 */
class MarketerDefaultCommissionResolver
{
    public const SOURCE_CATEGORY = 'category';

    public const SOURCE_VERTICAL = 'vertical';

    public const SOURCE_NONE = 'none';

    /** @var array<string, ?MarketerDefaultCommission> */
    private array $cache = [];

    /** Influencer fixed amount per campaign (0 when nothing is configured). */
    public function influencerFixedAmount(?string $countryId, CommissionVertical|string $vertical, ?string $categoryId = null): float
    {
        return $this->influencerFixed($countryId, $vertical, $categoryId)['amount'];
    }

    /**
     * @return array{amount: float, source: string} source = category | vertical | none
     */
    public function influencerFixed(?string $countryId, CommissionVertical|string $vertical, ?string $categoryId = null): array
    {
        $category = $this->row($countryId, $vertical, $categoryId);
        if ($category) {
            return ['amount' => round((float) $category->influencer_fixed_amount, 4), 'source' => self::SOURCE_CATEGORY];
        }

        $wide = $this->row($countryId, $vertical, null);
        if ($wide) {
            return ['amount' => round((float) $wide->influencer_fixed_amount, 4), 'source' => self::SOURCE_VERTICAL];
        }

        return ['amount' => 0.0, 'source' => self::SOURCE_NONE];
    }

    /**
     * Default commission fee for the country + vertical.
     *
     * @return array{percentage: float, fixed: float, source: string}
     */
    public function commissionFee(?string $countryId, CommissionVertical|string $vertical, ?string $categoryId = null): array
    {
        $category = $this->row($countryId, $vertical, $categoryId);
        if ($category && ((float) $category->commission_fee_percentage > 0 || (float) $category->commission_fee_fixed > 0)) {
            return [
                'percentage' => (float) $category->commission_fee_percentage,
                'fixed' => round((float) $category->commission_fee_fixed, 4),
                'source' => self::SOURCE_CATEGORY,
            ];
        }

        $wide = $this->row($countryId, $vertical, null);
        if ($wide) {
            return [
                'percentage' => (float) $wide->commission_fee_percentage,
                'fixed' => round((float) $wide->commission_fee_fixed, 4),
                'source' => self::SOURCE_VERTICAL,
            ];
        }

        return ['percentage' => 0.0, 'fixed' => 0.0, 'source' => self::SOURCE_NONE];
    }

    private function row(?string $countryId, CommissionVertical|string $vertical, ?string $categoryId): ?MarketerDefaultCommission
    {
        if (! $countryId) {
            return null;
        }

        $type = $vertical instanceof CommissionVertical ? $vertical->value : $vertical;
        $key = implode('|', [$countryId, $type, $categoryId ?? '-']);

        if (! array_key_exists($key, $this->cache)) {
            $this->cache[$key] = MarketerDefaultCommission::query()
                ->where('country_id', $countryId)
                ->where('category_type', $type)
                ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId), fn ($q) => $q->whereNull('category_id'))
                ->first();
        }

        return $this->cache[$key];
    }
}
