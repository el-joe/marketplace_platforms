<?php

declare(strict_types=1);

namespace App\Services\Shared;

use App\Models\Country;
use App\Models\Setting;

/**
 * The platform-wide "fixed" commission, set once for every category: one percentage for all countries
 * (settings.fixed_commission_pct) plus a flat per-unit amount per country (countries.fixed_commission_amount).
 * It is added to the category-driven ("dynamic") commission:
 *
 *   commission = (fixed % x base / 100 + country amount x qty) + dynamic commission
 */
class FixedCommissionService
{
    public const PERCENT_SETTING_KEY = 'fixed_commission_pct';

    public function percentage(): float
    {
        return (float) Setting::get(self::PERCENT_SETTING_KEY, 0);
    }

    public function amountFor(Country $country): float
    {
        return (float) $country->fixed_commission_amount;
    }

    /**
     * @param  int|float  $baseAmount  Line total (unit price x quantity) the percentage applies to
     */
    public function calculate(int|float $baseAmount, ?Country $country, int $quantity = 1): int|float
    {
        $pct = $this->percentage();
        $perUnit = $country !== null ? $this->amountFor($country) : 0.0;

        if ($pct <= 0.0 && $perUnit <= 0.0) {
            return 0;
        }

        $percentPortion = $pct > 0.0 ? floor((float) $baseAmount * $pct / 100) : 0;
        $total = round($percentPortion + $perUnit * max(1, $quantity), 4);

        return floor($total) == $total ? (int) $total : $total;
    }

    /**
     * @param  array<string, float|int|string|null>  $amountsByCountryId
     */
    public function update(float $pct, array $amountsByCountryId, ?string $adminId = null): void
    {
        Setting::updateOrCreate(
            ['key' => self::PERCENT_SETTING_KEY],
            [
                'value' => $pct,
                'category' => 'general',
                'description' => 'Global fixed commission percentage, charged on top of the category commission',
                'is_encrypted' => 0,
                'is_public' => 0,
                'updated_by_admin_id' => $adminId,
            ]
        );

        foreach ($amountsByCountryId as $countryId => $amount) {
            Country::whereKey($countryId)->update(['fixed_commission_amount' => (float) ($amount ?? 0)]);
        }

        Setting::flush();
    }
}
