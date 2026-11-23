<?php

namespace App\Enums;

/**
 * Outcome of distance-based delivery pricing for one origin → customer leg (client item #19).
 */
enum DistanceDeliveryStatus: string
{
    /** Customer is beyond the rule's max service radius: the method cannot be used. */
    case Unavailable = 'unavailable';

    /** The order-amount free-shipping threshold of the zone rate was met. */
    case FreeByOrderAmount = 'free_by_order_amount';

    /** Within the free-delivery radius. */
    case FreeWithinRadius = 'free_within_radius';

    /** Priced by a distance tier. */
    case Tier = 'tier';

    /** Distance pricing could not be applied (missing coordinates / no tier covers the distance): normal zone pricing is used. */
    case Fallback = 'fallback';

    public function isDistancePriced(): bool
    {
        return in_array($this, [self::FreeByOrderAmount, self::FreeWithinRadius, self::Tier], true);
    }
}
