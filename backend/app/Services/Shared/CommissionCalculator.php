<?php

declare(strict_types=1);

namespace App\Services\Shared;

/**
 * Pure-math commission calculation with optional price-tier switching and a
 * minimum floor. All money values are base-currency integers; the only /100
 * is the percentage itself.
 */
class CommissionCalculator
{
    /**
     * @param  int|float  $baseAmount  Line total (unit price × quantity) the percentage applies to
     * @param  float  $standardRate  Commission % when unit price > threshold (or tiering disabled)
     * @param  int  $thresholdPrice  Unit-price threshold; 0 = no tiering
     * @param  float  $highRate  Commission % when unit price <= threshold (0 = fall back to standardRate)
     * @param  int|float  $minCommission  Per-unit floor on the final commission; 0 = none
     * @param  int  $quantity  Units on the line
     * @param  int  $flatAmount  Flat amount per unit, added when $includeFlat is true
     */
    public static function calculate(
        int|float $baseAmount,
        float $standardRate,
        int $thresholdPrice = 0,
        float $highRate = 0.0,
        int|float $minCommission = 0,
        int $quantity = 1,
        int|float $flatAmount = 0,
        bool $includeFlat = false,
    ): int|float {
        $quantity = max(1, $quantity);
        $unitPrice = $baseAmount / $quantity;

        $activeRate = $standardRate;
        if ($thresholdPrice > 0 && $highRate > 0.0 && $unitPrice <= $thresholdPrice) {
            $activeRate = $highRate;
        }

        $percentPortion = 0;
        if ($activeRate > 0.0) {
            $raw = bcdiv(bcmul((string) $baseAmount, (string) $activeRate, 4), '100', 4);
            $percentPortion = round((float) $raw, 2);
        }

        $calculated = $percentPortion + ($includeFlat ? $flatAmount * $quantity : 0);

        if ($minCommission > 0) {
            $calculated = max($calculated, $minCommission * $quantity);
        }

        $result = max(0, round((float) $calculated, 4));

        return floor($result) == $result ? (int) $result : $result;
    }
}
