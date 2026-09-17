<?php

namespace App\Services\Customer;

use App\Enums\GlobalSystemType;
use App\Models\Address;
use App\Models\Cart;
use App\Models\City;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Country;
use App\Models\Customer;
use App\Models\Order;
use App\Models\ShippingRate;
use App\Models\VendorListing;
use App\Models\WarrantyPlan;
use App\Services\Checkout\CheckoutPricingEngine;
use App\Services\WarrantyPlanService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * @deprecated The coupon-discount and warranty-selection math previously
 * duplicated here now lives in App\Services\Checkout\CheckoutPricingEngine
 * (enhancement.md P-01). The methods below are thin delegates kept for
 * backward compatibility with callers that have not been migrated yet
 * (e.g. App\Services\Customer\CartService). Shipping and commission
 * calculation are unaffected and remain here.
 */
class CheckoutCalculationService
{
    /**
     * Resolve destination zone, applicable rate, weight fee and COD eligibility
     * for a shipping method and cart.
     *
     * @param  iterable<\App\Models\CartItem>  $cartItems
     */
    public function calculateShipping(
        Address $shippingAddress,
        Country $country,
        string $shippingMethodId,
        array $cartItems,
        bool $isCOD = false,
    ): array {
        $city = City::where('id', $shippingAddress->city_id)
            ->where('is_active', 1)
            ->first();

        if (! $city || ! $city->shipping_zone_id) {
            return [
                'fee' => 0,
                'cod_extra_fee' => 0,
                'is_free' => true,
                'error' => null,
                'cod_available' => false,
            ];
        }

        $rate = ShippingRate::where('destination_zone_id', $city->shipping_zone_id)
            ->where('shipping_method_id', $shippingMethodId)
            ->where('is_active', 1)
            ->whereNull('origin_zone_id')
            ->orderBy('base_fee')
            ->first();

        if (! $rate) {
            return [
                'fee' => 0,
                'cod_extra_fee' => 0,
                'is_free' => true,
                'error' => null,
                'cod_available' => (bool) ($city->cod_available && $country->cod_available),
            ];
        }

        $totalWeightGrams = 0;
        foreach ($cartItems as $item) {
            $weightGrams = (int) ($item->vendorListing?->productVariant?->weight_grams ?? 0);
            $totalWeightGrams += $weightGrams * $item->quantity;
        }

        $weightFee = $totalWeightGrams > $rate->min_weight_grams
            ? (int) ceil(($totalWeightGrams / 1000) * $rate->rate_per_kg)
            : 0;

        $cartSubtotal = 0;
        foreach ($cartItems as $item) {
            $cartSubtotal += $item->unit_price * $item->quantity;
        }

        $shippingFee = $rate->base_fee + $weightFee;

        $isFree = $rate->free_shipping_threshold !== null
            && $cartSubtotal >= $rate->free_shipping_threshold;

        if ($isFree) {
            $shippingFee = 0;
        }

        $codFee = ($isCOD && $rate->cod_extra_fee > 0) ? $rate->cod_extra_fee : 0;
        $codAvailable = (bool) ($city->cod_available && $country->cod_available);

        return [
            'fee' => $shippingFee,
            'cod_extra_fee' => $codFee,
            'is_free' => $isFree,
            'error' => null,
            'cod_available' => $codAvailable,
            'rate_id' => $rate->id,
            'carrier_id' => $rate->carrier_id,
            'zone_id' => $city->shipping_zone_id,
        ];
    }

    public function __construct(
        private readonly CheckoutPricingEngine $pricingEngine,
    ) {}

    public function calculateTax(int $taxableAmountCents, Country $country): int
    {
        return $this->pricingEngine->calculateTax($taxableAmountCents, $country);
    }

    public function calculateCommission(
        VendorListing $listing,
        int $quantity,
        int $unitPriceCents,
        ?Country $country = null,
    ): array {
        $isFBN = $listing->global_system_type === GlobalSystemType::ExpressFbn;
        $category = $listing->productVariant?->product?->category;

        $resolvedCategory = $this->resolveCommissionCategory($category, $isFBN);

        $countryCategory = ($country !== null && $resolvedCategory !== null)
            ? \App\Models\CountryCategory::where('country_id', $country->id)
                ->where('category_id', $resolvedCategory->id)
                ->first()
            : null;

        $pct = (float) ($isFBN
            ? ($countryCategory?->commission_fbn_pct ?? $resolvedCategory?->commission_fbn_pct)
            : ($countryCategory?->commission_fbp_pct ?? $resolvedCategory?->commission_fbp_pct)) ?: 0.0;
        $fixed = (int) ($isFBN
            ? ($countryCategory?->commission_fbn_fixed ?? $resolvedCategory?->commission_fbn_fixed)
            : ($countryCategory?->commission_fbp_fixed ?? $resolvedCategory?->commission_fbp_fixed)) ?: 0;

        $lineSubtotal = $unitPriceCents * $quantity;
        $pctComponent = (int) floor($lineSubtotal * $pct / 100);
        $fixedComponent = $fixed * $quantity;
        $commissionAmount = $pctComponent + $fixedComponent;

        return [
            'commission_rate_pct' => $pct,
            'commission_fixed' => $fixed,
            'commission_amount' => $commissionAmount,
            'commission_category_id' => $resolvedCategory?->id,
            'vendor_payout_share' => $lineSubtotal - $commissionAmount,
        ];
    }

    /**
     * Walk up the category parent chain (max 5 levels) looking for the first
     * category with a non-zero commission rate or fixed fee for the given
     * fulfillment type.
     */
    private function resolveCommissionCategory(?\App\Models\Category $category, bool $isFBN): ?\App\Models\Category
    {
        $current = $category;
        $levels = 0;

        while ($current !== null && $levels < 5) {
            $pct = (float) ($isFBN ? $current->commission_fbn_pct : $current->commission_fbp_pct);
            $fixed = (int) ($isFBN ? $current->commission_fbn_fixed : $current->commission_fbp_fixed);

            if ($pct > 0 || $fixed > 0) {
                return $current;
            }

            $current = $current->parent;
            $levels++;
        }

        return $category;
    }

    /**
     * @deprecated Delegates to CheckoutPricingEngine::applyCoupon() — the
     * single source of truth for coupon discount math (enhancement.md P-01).
     *
     * @param  array<\App\Models\CartItem>  $cartItems
     */
    public function applyCoupon(
        Coupon $coupon,
        Customer $customer,
        int $subtotalCents,
        string $currency,
        array $cartItems,
    ): array {
        return $this->pricingEngine->applyCoupon($coupon, $customer, $subtotalCents, $currency, $cartItems);
    }

    /**
     * Apply an affiliate/marketer promo code (Cart.affiliate_promo_code_id) to
     * the order subtotal. Mirrors the validation pattern of applyCoupon() but
     * against the simpler AffiliatePromoCode model (no scoping/eligibility
     * rules — those codes apply to the whole cart subtotal).
     */
    public function applyAffiliatePromoCode(
        \App\Models\AffiliatePromoCode $promoCode,
        int $subtotalCents,
        string $currency,
    ): array {
        if (! $promoCode->is_active) {
            return ['discount' => 0, 'error' => 'Promo code is not active'];
        }

        $now = Carbon::now();
        if (($promoCode->valid_from && $now->lt($promoCode->valid_from))
            || ($promoCode->valid_until && $now->gt($promoCode->valid_until))) {
            return ['discount' => 0, 'error' => 'Promo code is not valid at this time'];
        }

        if ($promoCode->currency !== null && $promoCode->currency !== $currency) {
            return ['discount' => 0, 'error' => 'Promo code currency does not match'];
        }

        if ($promoCode->min_order_amount !== null && $subtotalCents < $promoCode->min_order_amount) {
            return ['discount' => 0, 'error' => 'Order does not meet minimum amount for this promo code'];
        }

        if ($promoCode->usage_limit_total !== null && $promoCode->times_used >= $promoCode->usage_limit_total) {
            return ['discount' => 0, 'error' => 'Promo code usage limit reached'];
        }

        $discount = $promoCode->type === 'percentage'
            ? (int) round($subtotalCents * ((float) $promoCode->value / 100))
            : (int) round((float) $promoCode->value);

        if ($promoCode->max_discount !== null && $discount > $promoCode->max_discount) {
            $discount = $promoCode->max_discount;
        }

        $discount = min($discount, $subtotalCents);

        return ['discount' => $discount, 'error' => null];
    }

    /**
     * @deprecated Kept only as a legacy-shape summary (no per-line detail).
     * New code should call CheckoutPricingEngine::priceCart() directly to
     * get a PricedCart with reconciled per-line/sub-order/order figures.
     */
    public function buildOrderSummary(
        array $cartItems,
        int $shippingFeeCents,
        int $codFeeCents,
        int $discountCents,
        Country $country,
        int $giftCardAppliedCents = 0,
        int $warrantyTotalCents = 0,
    ): array {
        $subtotal = 0;
        foreach ($cartItems as $item) {
            $subtotal += $item->unit_price * $item->quantity;
        }

        $taxable = max(0, $subtotal - $discountCents);
        $tax = $this->calculateTax($taxable, $country);
        $total = max(0, $subtotal - $discountCents + $shippingFeeCents + $codFeeCents + $tax + $warrantyTotalCents - $giftCardAppliedCents);

        return [
            'subtotal' => $subtotal,
            'discount' => $discountCents,
            'shipping' => $shippingFeeCents,
            'cod_fee' => $codFeeCents,
            'tax' => $tax,
            'warranty_total' => $warrantyTotalCents,
            'gift_card_applied' => $giftCardAppliedCents,
            'total' => $total,
            'currency' => $country->currency_code,
        ];
    }

    /**
     * @deprecated Delegates to CheckoutPricingEngine::resolveWarrantySelections()
     * — the single source of truth for warranty selection resolution
     * (enhancement.md P-01).
     *
     * @param  array<\App\Models\CartItem>  $cartItems
     * @param  array<int, array{listing_id: string, warranty_plan_id: string}>  $warrantySelections
     * @return array{selections: array<string, array{plan: WarrantyPlan, price: int}>, total: int}
     */
    public function resolveWarrantySelections(
        array $cartItems,
        array $warrantySelections,
        Country $country,
        string $currency,
        WarrantyPlanService $warrantyPlanService,
    ): array {
        return $this->pricingEngine->resolveWarrantySelections($cartItems, $warrantySelections, $country, $currency);
    }
}
