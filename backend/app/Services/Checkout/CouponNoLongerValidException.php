<?php

namespace App\Services\Checkout;

/**
 * Thrown when a coupon that passed initial validation turns out to be
 * ineligible by the time it is re-evaluated under lock at placement (e.g.
 * a concurrent order just exhausted usage_limit_total). Caught by
 * Customer\CheckoutController and surfaced as a 422 with a localized
 * message.
 */
class CouponNoLongerValidException extends \RuntimeException
{
}
