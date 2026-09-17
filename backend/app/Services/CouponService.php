<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Customer;
use App\Models\Order;
use App\Enums\CouponCustomerEligibility;
use App\Enums\CouponScope;
use App\Enums\CouponType;
use App\Enums\OrderStatus;
use App\Services\Checkout\CheckoutPricingEngine;
use App\Services\Checkout\CouponEligibilityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CouponService
{
    public function __construct(
        private readonly CheckoutPricingEngine $pricingEngine,
        private readonly CouponEligibilityService $couponEligibilityService = new CouponEligibilityService(new CheckoutPricingEngine(new \App\Services\WarrantyPlanService())),
    ) {}

    /**
     * enhancement.md P-04: delegates to CouponEligibilityService (backed by
     * CheckoutPricingEngine::applyCoupon — the single source of truth for
     * coupon rules) instead of re-implementing the rules a third time. This
     * is the path CustomerCouponController uses to apply a coupon to the
     * cart before checkout.
     */
    public function validate(string $code, Cart $cart, Customer $customer): Coupon
    {
        $coupon = Coupon::whereRaw('UPPER(code) = ?', [strtoupper($code)])->first();

        if (!$coupon) {
            throw ValidationException::withMessages([
                'coupon' => __('common.exceptions.checkout.invalid_coupon'),
            ]);
        }

        $items = $cart->items()->with([
            'vendorListing.productVariant.product',
            'adminListing.productVariant.product',
        ])->get()->all();

        $result = $this->couponEligibilityService->evaluate(
            $coupon,
            $customer,
            (int) $cart->subtotal,
            $cart->currency,
            $items,
            $cart->country_id,
            (bool) $cart->affiliate_promo_code_id,
        );

        if ($result['error']) {
            throw ValidationException::withMessages([
                'coupon' => $result['error'],
            ]);
        }

        return $coupon;
    }

    /**
     * @deprecated Delegates to CheckoutPricingEngine::applyCoupon() — the
     * single source of truth for coupon discount math (enhancement.md P-01).
     * Note: unlike the engine's percentage rounding (round()), this method
     * historically floored the percentage discount; that behaviour is no
     * longer preserved now that the math lives in one place.
     */
    public function calculateDiscount(Coupon $coupon, Cart $cart): int
    {
        $items = $cart->items()->with([
            'vendorListing.productVariant.product',
            'adminListing.productVariant.product',
        ])->get()->all();

        $customer = $cart->customer;
        if (! $customer) {
            return 0;
        }

        if ($coupon->type === CouponType::FreeShipping) {
            $shipping = (int) $cart->estimated_shipping;

            return $coupon->max_discount !== null ? min($shipping, (int) $coupon->max_discount) : $shipping;
        }

        $result = $this->pricingEngine->applyCoupon($coupon, $customer, (int) $cart->subtotal, $cart->currency, $items);

        return $result['error'] ? 0 : $result['discount'];
    }

    /**
     * @deprecated enhancement.md P-04: place-order now uses
     * Checkout\CouponUsageService::reserve()/consumeForOrder()/
     * releaseForOrder() so usage is only permanent once payment actually
     * succeeds, and is reverted on decline/cancel/RTO. Kept only for any
     * caller outside the checkout flow that still needs a one-shot
     * "usage happened, no reversal" record.
     */
    public function recordUsage(Coupon $coupon, Order $order, Customer $customer, int $discountAmount): void
    {
        CouponUsage::create([
            'coupon_id' => $coupon->id,
            'customer_id' => $customer->id,
            'order_id' => $order->id,
            'discount_amount' => $discountAmount,
            'used_at' => now(),
            'status' => CouponUsage::STATUS_CONSUMED,
        ]);

        DB::table('coupons')
            ->where('id', $coupon->id)
            ->increment('times_used');
    }
}
