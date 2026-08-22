<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\ShippingRate;

/**
 * All weight values in GRAMS (integers).
 * All dimension values in CM (decimals).
 * All money values in BIGINT base currency units.
 */
class ShippingWeightService
{
    /**
     * Volumetric weight in grams: CEIL((L_cm x W_cm x H_cm) / 5.0).
     * The 5.0 divisor is the industry-standard ÷5000 for kg, x1000 for grams, simplified.
     */
    public function volumetricWeightGrams(float $lengthCm, float $widthCm, float $heightCm): int
    {
        return (int) ceil(($lengthCm * $widthCm * $heightCm) / 5.0);
    }

    /**
     * Billable weight = MAX(actual_grams, volumetric_grams).
     * If any dimension is missing, actual weight is used as-is.
     */
    public function billableWeightGrams(
        int $actualWeightGrams,
        ?float $lengthCm,
        ?float $widthCm,
        ?float $heightCm
    ): int {
        if ($lengthCm === null || $widthCm === null || $heightCm === null) {
            return $actualWeightGrams;
        }

        return max($actualWeightGrams, $this->volumetricWeightGrams($lengthCm, $widthCm, $heightCm));
    }

    /**
     * Thresholds are configurable in Admin Settings (weight_class_light_max_grams,
     * weight_class_medium_max_grams). Anything above the medium threshold is heavy.
     */
    public function classifyWeight(int $billableWeightGrams): string
    {
        $lightMax = (int) Setting::get('weight_class_light_max_grams', 1000);
        $mediumMax = (int) Setting::get('weight_class_medium_max_grams', 5000);

        if ($billableWeightGrams <= $lightMax) {
            return 'light';
        }
        if ($billableWeightGrams <= $mediumMax) {
            return 'medium';
        }

        return 'heavy';
    }

    /**
     * raw fee = base_fee + (rate_per_kg x billable_weight_grams / 1000), BIGINT-safe.
     */
    public function computeRawShippingFee(ShippingRate $rate, int $billableWeightGrams): int
    {
        $weightFee = intdiv($rate->rate_per_kg * $billableWeightGrams, 1000);

        return $rate->base_fee + $weightFee;
    }
}
