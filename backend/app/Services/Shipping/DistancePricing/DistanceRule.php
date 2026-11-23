<?php

namespace App\Services\Shipping\DistancePricing;

use App\Models\ShippingDistancePricing;

/**
 * Framework-free snapshot of a distance pricing rule, so DistanceFeeCalculator stays pure.
 */
final readonly class DistanceRule
{
    /**
     * @param  list<DistanceTier>  $tiers  sorted by from_km
     */
    public function __construct(
        public ?float $freeRadiusKm,
        public ?float $maxServiceRadiusKm,
        public array $tiers,
    ) {}

    public static function fromModel(ShippingDistancePricing $pricing): self
    {
        return new self(
            freeRadiusKm: $pricing->free_radius_km,
            maxServiceRadiusKm: $pricing->max_service_radius_km,
            tiers: $pricing->tiers
                ->map(fn ($tier) => new DistanceTier((float) $tier->from_km, $tier->to_km === null ? null : (float) $tier->to_km, $tier->price))
                ->sortBy('fromKm')
                ->values()
                ->all(),
        );
    }

    public function tierFor(float $distanceKm): ?DistanceTier
    {
        foreach ($this->tiers as $tier) {
            if ($tier->covers($distanceKm)) {
                return $tier;
            }
        }

        return null;
    }
}
