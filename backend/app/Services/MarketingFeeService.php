<?php

namespace App\Services;

use App\Enums\MarketingFeeType;
use App\Models\MarketingFeeCategorySetting;
use App\Models\MarketingFeeCountrySetting;

/**
 * Marketing fees charged on top of a campaign subtotal (no VAT on them):
 *   fixed_fee    = fixed_amount(country)            + subtotal x fixed_percentage(country) / 100
 *   variable_fee = amount(country, category, type)  + subtotal x percentage(country, category, type) / 100
 *   marketing    = fixed_fee + variable_fee
 * Each part is rounded half up to 2 decimals; a zero subtotal yields zero.
 */
class MarketingFeeService
{
    /**
     * @param  bool  $applyFlatAmounts  false drops both flat amounts and keeps only the percentage parts
     *                                  (affiliate accruals after the first order: flat amounts are charged once).
     * @return array{fixed_fee: float, variable_fee: float, total_fee: float}
     */
    public function calculate(
        int|float|string|null $subtotal,
        ?string $countryId,
        ?string $categoryId,
        MarketingFeeType $type,
        bool $applyFlatAmounts = true,
    ): array {
        $subtotal = (float) $subtotal;
        if ($subtotal <= 0) {
            return ['fixed_fee' => 0.0, 'variable_fee' => 0.0, 'total_fee' => 0.0];
        }

        $country = $countryId ? MarketingFeeCountrySetting::where('country_id', $countryId)->first() : null;
        $category = MarketingFeeCategorySetting::forCategory($countryId, $categoryId, $type);

        $fixed = $this->part($subtotal, $country?->fixed_amount, $country?->fixed_percentage, $applyFlatAmounts);
        $variable = $this->part($subtotal, $category?->fixed_amount, $category?->percentage, $applyFlatAmounts);

        return [
            'fixed_fee' => $fixed,
            'variable_fee' => $variable,
            'total_fee' => round($fixed + $variable, 2),
        ];
    }

    private function part(float $subtotal, int|float|null $amount, int|float|string|null $pct, bool $applyAmount): float
    {
        $flat = $applyAmount ? (float) $amount : 0.0;

        return round($flat + $subtotal * ((float) $pct / 100), 2);
    }
}
