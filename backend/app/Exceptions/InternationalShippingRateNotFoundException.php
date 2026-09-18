<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * docs/plans/international_product_shipping.md Phase 2: thrown when no active
 * international_shipping_rates row exists for a given origin/destination
 * country corridor. Callers must never silently fall back to a domestic rate
 * or zero — this exception is how that constraint is enforced.
 */
class InternationalShippingRateNotFoundException extends RuntimeException
{
    public function __construct(string $originCountryId, string $destinationCountryId)
    {
        parent::__construct(
            "No active international shipping rate found for corridor {$originCountryId} -> {$destinationCountryId}."
        );
    }
}
