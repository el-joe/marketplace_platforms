<?php

namespace App\Services;

use App\Models\Marketer;
use App\Models\MarketerCommissionRule;

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
     * Resolve the marketer-specific rule (category override, then the
     * marketer default), or null when none exists.
     */
    public function resolveRule(Marketer|string $marketer, ?string $categoryId, string $scope = 'products'): ?MarketerCommissionRule
    {
        $marketerId = $marketer instanceof Marketer ? $marketer->id : $marketer;

        return app(CommissionRuleResolver::class)->resolve($marketerId, $scope, $categoryId);
    }

    /**
     * @return float Percentage rate, e.g. 8.00 = 8%. Flat amounts are ignored here.
     */
    public function resolveRate(Marketer|string $marketer, ?string $categoryId, string $scope = 'products'): float
    {
        return (float) ($this->resolveRule($marketer, $categoryId, $scope)?->commission_rate ?? 0);
    }

    /**
     * Apply the resolved rule (fixed | percentage | both) to a base-currency
     * line total. The flat part is per unit, so it is multiplied by $quantity
     * (open-market ads use quantity 1). "both" = percentage of base + flat.
     * Accepts a decimal string so fractional cents aren't truncated first.
     */
    public function calculateCommissionAmount(Marketer|string $marketer, ?string $categoryId, int|string $baseAmount, int $quantity = 1, string $scope = 'products'): int
    {
        return $this->resolveRule($marketer, $categoryId, $scope)?->resolveAmount($baseAmount, $quantity) ?? 0;
    }
}
