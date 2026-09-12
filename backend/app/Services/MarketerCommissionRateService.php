<?php

namespace App\Services;

use App\Models\Marketer;
use App\Models\MarketerCategoryCommission;

/**
 * Resolves the post-sale commission rate for a marketer × category pair.
 *
 * Resolution order:
 *   1. A marketer-specific override for the given category (category_id = $categoryId).
 *   2. The marketer's default rate (category_id = null).
 *   3. 0.
 */
class MarketerCommissionRateService
{
    /**
     * @return float Percentage rate, e.g. 8.00 = 8%.
     */
    public function resolveRate(Marketer|string $marketer, ?string $categoryId): float
    {
        $marketerId = $marketer instanceof Marketer ? $marketer->id : $marketer;

        $rows = MarketerCategoryCommission::where('marketer_id', $marketerId)
            ->when($categoryId, fn ($q) => $q->whereIn('category_id', [$categoryId, null]), fn ($q) => $q->whereNull('category_id'))
            ->get()
            ->keyBy('category_id');

        if ($categoryId && $rows->has($categoryId)) {
            return (float) $rows->get($categoryId)->commission_rate;
        }

        if ($rows->has(null)) {
            return (float) $rows->get(null)->commission_rate;
        }

        return 0.0;
    }

    /**
     * Resolve the rate and apply it to a base-currency integer amount, preserving
     * the codebase convention of storing money as integer minor units computed
     * via floor() (see CouponService, CheckoutCalculationService, etc.).
     */
    public function calculateCommissionAmount(Marketer|string $marketer, ?string $categoryId, int $baseAmount): int
    {
        $rate = $this->resolveRate($marketer, $categoryId);

        if ($rate <= 0) {
            return 0;
        }

        return (int) floor($baseAmount * $rate / 100);
    }
}
