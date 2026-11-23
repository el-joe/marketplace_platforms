<?php

namespace App\Services\Marketer;

use App\Enums\CommissionVertical;
use App\Models\ClassifiedCategory;
use App\Models\MarketerDefaultCommission;

/**
 * Platform defaults configured on the admin "Marketer Settings" page and on the classified category page
 * (marketer_default_commissions).
 *
 * Lookup order for a country + vertical (+ optional category), per value:
 *   1. the category's own row, when it sets that value;
 *   2. classified vertical only: the nearest ancestor category's row that sets it (a new sub-category inherits its
 *      section's settings automatically);
 *   3. the vertical-wide row (category_id NULL);
 *   4. nothing configured => 0.
 *
 * A NULL column on a category row means "inherit"; 0 is an explicit zero.
 * - influencer fixed amount: the ONE amount an influencer earns per campaign (0 = explicitly free).
 * - commission fee (pct + fixed): taken from every marketer commission. A category row with a fee above zero provides
 *   the fee (a part it leaves NULL comes from the vertical-wide row); a 0/0 fee row does not shadow the vertical fee,
 *   so a per-category fee of exactly zero cannot be expressed (unchanged from before).
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
        foreach ($this->categoryRows($countryId, $vertical, $categoryId) as $category) {
            if ($category->influencer_fixed_amount !== null) {
                return ['amount' => round((float) $category->influencer_fixed_amount, 4), 'source' => self::SOURCE_CATEGORY];
            }
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
        $wide = $this->row($countryId, $vertical, null);

        foreach ($this->categoryRows($countryId, $vertical, $categoryId) as $category) {
            // A row whose fee is 0/0 (or NULL) sets no fee, as before: it never shadows the vertical-wide fee.
            if ((float) ($category->commission_fee_percentage ?? 0) > 0 || (float) ($category->commission_fee_fixed ?? 0) > 0) {
                return [
                    'percentage' => (float) ($category->commission_fee_percentage ?? $wide?->commission_fee_percentage ?? 0),
                    'fixed' => round((float) ($category->commission_fee_fixed ?? $wide?->commission_fee_fixed ?? 0), 4),
                    'source' => self::SOURCE_CATEGORY,
                ];
            }
        }

        if ($wide) {
            return [
                'percentage' => (float) $wide->commission_fee_percentage,
                'fixed' => round((float) $wide->commission_fee_fixed, 4),
                'source' => self::SOURCE_VERTICAL,
            ];
        }

        return ['percentage' => 0.0, 'fixed' => 0.0, 'source' => self::SOURCE_NONE];
    }

    /**
     * The category's own row, then (classified vertical) the rows of its ancestors, nearest first.
     *
     * @return list<MarketerDefaultCommission>
     */
    private function categoryRows(?string $countryId, CommissionVertical|string $vertical, ?string $categoryId): array
    {
        $type = $vertical instanceof CommissionVertical ? $vertical->value : $vertical;
        $rows = [];
        $seen = [];

        while ($categoryId && ! isset($seen[$categoryId]) && count($seen) < 10) {
            $seen[$categoryId] = true;
            if ($row = $this->row($countryId, $type, $categoryId)) {
                $rows[] = $row;
            }
            if ($type !== CommissionVertical::Classified->value) {
                break;
            }
            $categoryId = ClassifiedCategory::query()->whereKey($categoryId)->value('parent_id');
        }

        return $rows;
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
