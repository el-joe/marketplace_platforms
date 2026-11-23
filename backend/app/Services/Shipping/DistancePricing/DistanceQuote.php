<?php

namespace App\Services\Shipping\DistancePricing;

use App\Enums\DistanceDeliveryStatus;

final readonly class DistanceQuote
{
    public const REASON_MISSING_COORDINATES = 'missing_coordinates';

    public const REASON_NO_MATCHING_TIER = 'no_matching_tier';

    public function __construct(
        public DistanceDeliveryStatus $status,
        public ?float $distanceKm,
        public int|float $fee = 0,
        public ?DistanceTier $tier = null,
        public ?string $fallbackReason = null,
    ) {}
}
