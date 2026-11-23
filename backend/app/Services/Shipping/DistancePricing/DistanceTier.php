<?php

namespace App\Services\Shipping\DistancePricing;

/**
 * One distance band: from_km is inclusive, to_km exclusive; null to_km = open-ended.
 */
final readonly class DistanceTier
{
    public function __construct(
        public float $fromKm,
        public ?float $toKm,
        public int|float $price,
    ) {}

    public function covers(float $distanceKm): bool
    {
        $tenths = DistanceFeeCalculator::toTenths($distanceKm);

        return $tenths >= DistanceFeeCalculator::toTenths($this->fromKm)
            && ($this->toKm === null || $tenths < DistanceFeeCalculator::toTenths($this->toKm));
    }

    /**
     * @return array{from_km: float, to_km: ?float, price: int|float}
     */
    public function toArray(): array
    {
        return ['from_km' => $this->fromKm, 'to_km' => $this->toKm, 'price' => $this->price];
    }
}
