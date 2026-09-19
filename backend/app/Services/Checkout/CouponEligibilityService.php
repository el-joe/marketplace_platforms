<?php

namespace App\Services\Checkout;

use App\Models\Coupon;
use App\Models\Customer;

/**
 * enhancement.md P-04 task 1: the single entry point checkout uses to
 * decide whether a coupon applies, and for how much. All the actual rule
 * checks (active/window/country/currency/min-order/eligibility/per-customer/
 * per-month/total-limit/scope/shipping-type/stackability) and the discount
 * math (including free_shipping scope resolution) live in
 * CheckoutPricingEngine::applyCoupon() — the single source of truth for
 * coupon math established by P-01 — so this service is a thin, named
 * façade over it rather than a second copy of the rules.
 */
class CouponEligibilityService
{
    public function __construct(
        private readonly CheckoutPricingEngine $pricingEngine,
    ) {}

    /**
     * @param  array<int, mixed>  $items  Cart lines, in any shape
     *      CheckoutPricingEngine::applyCoupon() accepts.
     */
    public function evaluate(
        Coupon $coupon,
        ?Customer $customer,
        int $subtotalCents,
        string $currency,
        array $items,
        ?string $countryId = null,
        bool $stackedWithOtherDiscount = false,
    ): array {
        return $this->pricingEngine->applyCoupon(
            $coupon,
            $customer,
            $subtotalCents,
            $currency,
            $items,
            $countryId,
            $stackedWithOtherDiscount,
        );
    }
}
