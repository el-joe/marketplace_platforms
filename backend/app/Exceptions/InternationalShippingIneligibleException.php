<?php

namespace App\Exceptions;

use DomainException;

/**
 * docs/plans/international_product_shipping.md Phase 3: thrown when a cart
 * line's fulfilment listing's country differs from the order's destination
 * country and either (a) no active international_shipping_eligibility row
 * exists for that (listing, destination country) pair, or (b) an existing
 * product_countries row explicitly marks the product unavailable in that
 * destination country (that gate takes precedence over shipping
 * eligibility). Never silently allow an ineligible cross-border line through
 * checkout.
 */
class InternationalShippingIneligibleException extends DomainException
{
    public function __construct(string $message, public readonly ?string $listingId = null, public readonly ?string $destinationCountryId = null)
    {
        parent::__construct($message);
    }
}
