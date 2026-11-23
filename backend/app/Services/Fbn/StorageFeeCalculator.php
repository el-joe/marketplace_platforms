<?php

namespace App\Services\Fbn;

use App\Models\StorageFeeFreePeriodRule;
use Carbon\CarbonInterface;

/**
 * Pure FBN storage-fee math shared by GenerateFbnStorageFeesJob (monthly run)
 * and StorageFeeRecalculationService (explicit re-pricing of pending fees
 * after a free-period rule change).
 *
 * Fees are billed on the CHARGEABLE weight (max of actual vs. volumetric)
 * only after the free period of the rule effective for the storage start
 * date has elapsed; the fee itself is physical volume (m3) x rate x units.
 */
class StorageFeeCalculator
{
    /**
     * Volumetric weight divisor (cm^3 -> kg). The plan doc (section 5.1)
     * leaves this pending client confirmation between 5000 and 6000;
     * 5000 is the working default.
     */
    public const VOLUMETRIC_DIVISOR = 5000;

    public function volumetricWeightGrams(?float $lengthCm, ?float $widthCm, ?float $heightCm): int
    {
        if (! $lengthCm || ! $widthCm || ! $heightCm) {
            return 0;
        }

        return (int) round(($lengthCm * $widthCm * $heightCm) / self::VOLUMETRIC_DIVISOR * 1000);
    }

    public function volumeM3(?float $lengthCm, ?float $widthCm, ?float $heightCm): float
    {
        if (! $lengthCm || ! $widthCm || ! $heightCm) {
            return 0.0;
        }

        return ($lengthCm * $widthCm * $heightCm) / 1_000_000;
    }

    /**
     * @return array{free_days_applied: int, within_free_period: bool, total_fee: float}
     */
    public function charge(
        int $chargeableWeightGrams,
        CarbonInterface $storedSince,
        int $daysInStorage,
        float $volumeM3,
        float $ratePerM3,
        int $units,
    ): array {
        $freeDays = StorageFeeFreePeriodRule::freeDaysFor($chargeableWeightGrams, $storedSince);
        $withinFree = $daysInStorage <= $freeDays;

        return [
            'free_days_applied' => $freeDays,
            'within_free_period' => $withinFree,
            'total_fee' => $withinFree ? 0.0 : round($volumeM3 * $ratePerM3 * $units, 2),
        ];
    }
}
