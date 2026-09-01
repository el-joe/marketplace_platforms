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
use App\Services\WarrantyPlanService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

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

    public function calculateTax(int $taxableAmountCents, Country $country): int
    {
        return (int) round($taxableAmountCents * ((float) $country->vat_rate / 100));
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
     * @param  array<\App\Models\CartItem>  $cartItems
     */
    public function applyCoupon(
        Coupon $coupon,
        Customer $customer,
        int $subtotalCents,
        string $currency,
        array $cartItems,
    ): array {
        if (! $coupon->is_active) {
            return ['discount' => 0, 'error' => 'Coupon is not active'];
        }

        $now = Carbon::now();
        if (($coupon->valid_from && $now->lt($coupon->valid_from))
            || ($coupon->valid_until && $now->gt($coupon->valid_until))) {
            return ['discount' => 0, 'error' => 'Coupon is not valid at this time'];
        }

        if ($coupon->currency !== null && $coupon->currency !== $currency) {
            return ['discount' => 0, 'error' => 'Coupon currency does not match'];
        }

        if ($coupon->min_order_amount !== null && $subtotalCents < $coupon->min_order_amount) {
            return ['discount' => 0, 'error' => 'Order does not meet minimum amount for this coupon'];
        }

        if ($coupon->usage_limit_total !== null && $coupon->times_used >= $coupon->usage_limit_total) {
            return ['discount' => 0, 'error' => 'Coupon usage limit reached'];
        }

        if ($coupon->usage_limit_per_customer !== null) {
            $customerUsageCount = CouponUsage::where('coupon_id', $coupon->id)
                ->where('customer_id', $customer->id)
                ->count();

            if ($customerUsageCount >= $coupon->usage_limit_per_customer) {
                return ['discount' => 0, 'error' => 'You have already used this coupon the maximum number of times'];
            }
        }

        $applicableSubtotal = $this->resolveApplicableSubtotal($coupon, $subtotalCents, $cartItems);

        if ($applicableSubtotal <= 0) {
            return ['discount' => 0, 'error' => 'Coupon does not apply to any items in your cart'];
        }

        $discount = match ($coupon->type) {
            \App\Enums\CouponType::Percentage => (int) round($applicableSubtotal * ((float) $coupon->value / 100)),
            \App\Enums\CouponType::FixedAmount => (int) round((float) $coupon->value),
            \App\Enums\CouponType::FreeShipping => 0,
            \App\Enums\CouponType::Bogo => $this->cheapestQualifyingItemPrice($coupon, $cartItems),
            default => 0,
        };

        if ($coupon->max_discount !== null && $discount > $coupon->max_discount) {
            $discount = $coupon->max_discount;
        }

        $discount = min($discount, $applicableSubtotal);

        return [
            'discount' => $discount,
            'error' => null,
            'type' => $coupon->type?->value,
        ];
    }

    /**
     * @param  array<\App\Models\CartItem>  $cartItems
     */
    private function resolveApplicableSubtotal(Coupon $coupon, int $subtotalCents, array $cartItems): int
    {
        return match ($coupon->scope) {
            \App\Enums\CouponScope::Vendor => $this->sumItems($cartItems, fn ($item) => $item->vendorListing?->vendor_id === $coupon->vendor_id),
            \App\Enums\CouponScope::Category => $this->sumItems($cartItems, function ($item) use ($coupon) {
                $categoryId = $item->vendorListing?->productVariant?->product?->category_id;

                return $categoryId !== null && $this->categoryMatches($categoryId, $coupon->category_id);
            }),
            \App\Enums\CouponScope::Product => $this->sumItems($cartItems, function ($item) use ($coupon) {
                $productId = $item->vendorListing?->productVariant?->product_id;
                $vendorId = $item->vendorListing?->vendor_id;

                return $productId !== null
                    && $vendorId === $coupon->vendor_id
                    && $coupon->products()->where('products.id', $productId)->exists();
            }),
            default => $subtotalCents,
        };
    }

    /**
     * @param  array<\App\Models\CartItem>  $cartItems
     */
    private function sumItems(array $cartItems, \Closure $matcher): int
    {
        $sum = 0;
        foreach ($cartItems as $item) {
            if ($matcher($item)) {
                $sum += $item->unit_price * $item->quantity;
            }
        }

        return $sum;
    }

    private function categoryMatches(string $itemCategoryId, ?string $couponCategoryId): bool
    {
        if ($couponCategoryId === null) {
            return false;
        }

        if ($itemCategoryId === $couponCategoryId) {
            return true;
        }

        $category = \App\Models\Category::find($itemCategoryId);

        return $category !== null
            && $category->ancestors()->where('id', $couponCategoryId)->exists();
    }

    /**
     * @param  array<\App\Models\CartItem>  $cartItems
     */
    private function cheapestQualifyingItemPrice(Coupon $coupon, array $cartItems): int
    {
        $applicableItems = array_filter($cartItems, function ($item) use ($coupon) {
            return match ($coupon->scope) {
                \App\Enums\CouponScope::Vendor => $item->vendorListing?->vendor_id === $coupon->vendor_id,
                \App\Enums\CouponScope::Category => ($categoryId = $item->vendorListing?->productVariant?->product?->category_id) !== null
                    && $this->categoryMatches($categoryId, $coupon->category_id),
                \App\Enums\CouponScope::Product => ($productId = $item->vendorListing?->productVariant?->product_id) !== null
                    && $item->vendorListing?->vendor_id === $coupon->vendor_id
                    && $coupon->products()->where('products.id', $productId)->exists(),
                default => true,
            };
        });

        if (empty($applicableItems)) {
            return 0;
        }

        $prices = array_map(fn ($item) => (int) $item->unit_price, $applicableItems);

        return min($prices);
    }

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
     * Validate and price the customer's per-item warranty plan selections.
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
        $selections = [];
        $total = 0;

        foreach ($warrantySelections as $selection) {
            $listingId = $selection['listing_id'];
            $warrantyPlanId = $selection['warranty_plan_id'];

            $cartItem = collect($cartItems)->first(fn ($item) => $item->vendor_listing_id === $listingId);
            if (! $cartItem) {
                throw ValidationException::withMessages([
                    'warranty_selections' => "No cart item found for listing {$listingId}.",
                ]);
            }

            $plan = WarrantyPlan::active()->find($warrantyPlanId);
            if (! $plan) {
                throw ValidationException::withMessages([
                    'warranty_selections' => "Warranty plan {$warrantyPlanId} is not available.",
                ]);
            }

            $product = $cartItem->vendorListing->productVariant->product;
            $applicablePlanIds = collect($warrantyPlanService->getPlansForProduct($product, $country->id, $currency, (int) $cartItem->unit_price))
                ->pluck('id')
                ->all();

            if (! in_array($plan->id, $applicablePlanIds, true)) {
                throw ValidationException::withMessages([
                    'warranty_selections' => "Warranty plan {$warrantyPlanId} is not applicable to this product.",
                ]);
            }

            if ($plan->country_ids !== null && ! in_array($country->id, $plan->country_ids, true)) {
                throw ValidationException::withMessages([
                    'warranty_selections' => "Warranty plan {$warrantyPlanId} is not available in your country.",
                ]);
            }

            if ($plan->currency !== $currency) {
                throw ValidationException::withMessages([
                    'warranty_selections' => "Warranty plan {$warrantyPlanId} currency does not match order currency.",
                ]);
            }

            $resolvedPrice = $plan->resolvePrice((int) $cartItem->unit_price);

            $selections[$cartItem->id] = [
                'plan' => $plan,
                'price' => $resolvedPrice,
            ];

            $total += $resolvedPrice;
        }

        return ['selections' => $selections, 'total' => $total];
    }
}
