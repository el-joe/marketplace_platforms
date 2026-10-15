<?php

namespace App\Services;

use App\Enums\CommissionVertical;
use App\Enums\MarketingFeeType;
use App\Models\MarketingFeeCategorySetting;
use App\Models\MarketingFeeCountrySetting;
use App\Models\MarketingFeeVerticalSetting;

/**
 * The campaign fee the vendor / agency pays the platform, charged on the campaign total
 * (the Max Commission Budget; no VAT on it):
 *
 *   fixed_fee    = fixed_amount(country)            + total x fixed_percentage(country) / 100
 *   variable_fee = amount(country, category, type)  + total x percentage(country, category, type) / 100
 *   campaign fee = fixed_fee + variable_fee
 *
 * Each part is amount | percentage | both (whichever is configured), rounded half up to 2 decimals;
 * a zero total yields zero.
 *
 * Classified and travel campaigns have no product category tree: their variable part is configured per
 * country x vertical (marketing_fee_vertical_settings), see calculateForVertical().
 */
class MarketingFeeService
{
    /**
     * @return array{fixed_fee: float, variable_fee: float, total_fee: float}
     */
    public function calculate(
        int|float|string|null $subtotal,
        ?string $countryId,
        ?string $categoryId,
        MarketingFeeType $type,
    ): array {
        $subtotal = (float) $subtotal;
        if ($subtotal <= 0) {
            return ['fixed_fee' => 0.0, 'variable_fee' => 0.0, 'total_fee' => 0.0];
        }

        $country = $countryId ? MarketingFeeCountrySetting::where('country_id', $countryId)->first() : null;
        $category = MarketingFeeCategorySetting::forCategory($countryId, $categoryId, $type);

        $fixed = $this->part($subtotal, $country?->fixed_amount, $country?->fixed_percentage);
        $variable = $this->part($subtotal, $category?->fixed_amount, $category?->percentage);

        return [
            'fixed_fee' => $fixed,
            'variable_fee' => $variable,
            'total_fee' => round($fixed + $variable, 2),
        ];
    }

    /**
     * Campaign fee of a classified or travel campaign: the country-wide fixed part plus the vertical's own variable
     * part. Product campaigns go through calculate().
     *
     * @return array{fixed_fee: float, variable_fee: float, total_fee: float}
     */
    public function calculateForVertical(int|float|string|null $subtotal, ?string $countryId, CommissionVertical $vertical): array
    {
        $subtotal = (float) $subtotal;
        if ($subtotal <= 0) {
            return ['fixed_fee' => 0.0, 'variable_fee' => 0.0, 'total_fee' => 0.0];
        }

        $country = $countryId ? MarketingFeeCountrySetting::where('country_id', $countryId)->first() : null;
        $setting = MarketingFeeVerticalSetting::forVertical($countryId, $vertical);

        $fixed = $this->part($subtotal, $country?->fixed_amount, $country?->fixed_percentage);
        $variable = $this->part($subtotal, $setting?->fixed_amount, $setting?->percentage);

        return [
            'fixed_fee' => $fixed,
            'variable_fee' => $variable,
            'total_fee' => round($fixed + $variable, 2),
        ];
    }

    private function part(float $subtotal, int|float|null $amount, int|float|string|null $pct): float
    {
        return round((float) $amount + $subtotal * ((float) $pct / 100), 2);
    }
}
