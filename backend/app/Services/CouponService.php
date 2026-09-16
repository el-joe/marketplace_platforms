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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CouponService
{
    public function __construct(
        private readonly CheckoutPricingEngine $pricingEngine,
    ) {}
    public function validate(string $code, Cart $cart, Customer $customer): Coupon
    {
        $coupon = Coupon::whereRaw('UPPER(code) = ?', [strtoupper($code)])->first();

        if (!$coupon) {
            throw ValidationException::withMessages([
                'coupon' => __('Invalid coupon code'),
            ]);
        }

        if (!$coupon->is_active) {
            throw ValidationException::withMessages([
                'coupon' => __('This coupon is no longer active'),
            ]);
        }

        if (now()->lt($coupon->valid_from)) {
            throw ValidationException::withMessages([
                'coupon' => __('This coupon is not yet valid'),
            ]);
        }

        if (now()->gt($coupon->valid_until)) {
            throw ValidationException::withMessages([
                'coupon' => __('This coupon has expired'),
            ]);
        }

        if ($coupon->country_ids !== null) {
            $countryId = $cart->country_id;
            $isForCountry = Coupon::whereKey($coupon->id)->forCountry($countryId)->exists();

            if (!$isForCountry) {
                throw ValidationException::withMessages([
                    'coupon' => __('This coupon is not valid in your country'),
                ]);
            }
        }

        if (
            $coupon->type === CouponType::FixedAmount
            && $coupon->currency !== null
            && $coupon->currency !== $cart->currency
        ) {
            throw ValidationException::withMessages([
                'coupon' => __('This coupon is not valid for your currency'),
            ]);
        }

        if ($coupon->min_order_amount !== null && $cart->subtotal < $coupon->min_order_amount) {
            throw ValidationException::withMessages([
                'coupon' => __('Minimum order amount not reached'),
            ]);
        }

        if ($coupon->usage_limit_total !== null && $coupon->times_used >= $coupon->usage_limit_total) {
            throw ValidationException::withMessages([
                'coupon' => __('This coupon has reached its usage limit'),
            ]);
        }

        $usedByCustomer = CouponUsage::where('coupon_id', $coupon->id)
            ->where('customer_id', $customer->id)
            ->count();

        if ($usedByCustomer >= $coupon->usage_limit_per_customer) {
            throw ValidationException::withMessages([
                'coupon' => __('You have already used this coupon'),
            ]);
        }

        if ($coupon->max_orders_per_customer_per_month !== null) {
            $usedThisMonth = CouponUsage::where('coupon_id', $coupon->id)
                ->where('customer_id', $customer->id)
                ->whereYear('used_at', now()->year)
                ->whereMonth('used_at', now()->month)
                ->count();

            if ($usedThisMonth >= $coupon->max_orders_per_customer_per_month) {
                throw ValidationException::withMessages([
                    'coupon' => __('You have reached your monthly limit for this coupon'),
                ]);
            }
        }

        if ($coupon->customer_eligibility === CouponCustomerEligibility::NewCustomers) {
            $hasCompletedOrder = Order::where('customer_id', $customer->id)
                ->where('status', OrderStatus::Completed)
                ->exists();

            if ($hasCompletedOrder) {
                throw ValidationException::withMessages([
                    'coupon' => __('This coupon is for new customers only'),
                ]);
            }
        }

        if ($coupon->customer_eligibility === CouponCustomerEligibility::SpecificUsers) {
            $eligibleIds = $coupon->eligible_customer_ids ?? [];

            if (!in_array($customer->id, $eligibleIds, true)) {
                throw ValidationException::withMessages([
                    'coupon' => __('You are not eligible for this coupon'),
                ]);
            }
        }

        $this->validateScope($coupon, $cart);
        $this->validateShippingType($coupon, $cart);

        return $coupon;
    }

    /**
     * Coupons restricted to a shipping method only apply when every item in
     * the cart is fulfilled via that method.
     */
    protected function validateShippingType(Coupon $coupon, Cart $cart): void
    {
        if ($coupon->shipping_type_restriction === \App\Enums\CouponShippingTypeRestriction::All) {
            return;
        }

        $items = $cart->items()->with(['vendorListing', 'adminListing'])->get();

        $mismatched = $items->contains(function ($item) use ($coupon) {
            return $this->resolveItemShippingType($item) !== $coupon->shipping_type_restriction->value;
        });

        if ($mismatched) {
            throw ValidationException::withMessages([
                'coupon' => __('This coupon is only valid for :type shipping orders', [
                    'type' => strtoupper($coupon->shipping_type_restriction->value),
                ]),
            ]);
        }
    }

    private function resolveItemShippingType(\App\Models\CartItem $item): string
    {
        if ($item->adminListing !== null) {
            return 'fbn';
        }

        return match ($item->vendorListing?->fulfillment_model) {
            'fbn' => 'fbn',
            'cross_dock' => 'fbp',
            default => 'fbm',
        };
    }

    protected function validateScope(Coupon $coupon, Cart $cart): void
    {
        $items = $cart->items()->with([
            'vendorListing.productVariant',
            'adminListing.productVariant',
        ])->get();

        $productIds = $items->map(function ($item) {
            return $item->vendorListing?->productVariant?->product_id
                ?? $item->adminListing?->productVariant?->product_id;
        })->filter()->values();

        if ($coupon->scope === CouponScope::Vendor) {
            $vendorIds = $items->map(fn ($item) => $item->vendorListing?->vendor_id)->filter();

            if (!$vendorIds->contains($coupon->vendor_id)) {
                throw ValidationException::withMessages([
                    'coupon' => __('This coupon is not valid for items in your cart'),
                ]);
            }
        }

        if ($coupon->scope === CouponScope::Category) {
            $categoryIds = \App\Models\Product::whereIn('id', $productIds)->pluck('category_id');

            if (!$categoryIds->contains($coupon->category_id)) {
                throw ValidationException::withMessages([
                    'coupon' => __('This coupon is not valid for items in your cart'),
                ]);
            }
        }

        if ($coupon->scope === CouponScope::Product) {
            $couponProductIds = $coupon->products()->pluck('product_id');

            $matchingItems = $items->filter(function ($item) use ($couponProductIds, $coupon) {
                $productId = $item->vendorListing?->productVariant?->product_id
                    ?? $item->adminListing?->productVariant?->product_id;
                $vendorId = $item->vendorListing?->vendor_id;

                return $productId !== null
                    && $couponProductIds->contains($productId)
                    && $vendorId === $coupon->vendor_id;
            });

            if ($matchingItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'coupon' => __('This coupon is not valid for items in your cart'),
                ]);
            }
        }
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
            return (int) $cart->estimated_shipping;
        }

        $result = $this->pricingEngine->applyCoupon($coupon, $customer, (int) $cart->subtotal, $cart->currency, $items);

        return $result['error'] ? 0 : $result['discount'];
    }

    public function recordUsage(Coupon $coupon, Order $order, Customer $customer, int $discountAmount): void
    {
        CouponUsage::create([
            'coupon_id' => $coupon->id,
            'customer_id' => $customer->id,
            'order_id' => $order->id,
            'discount_amount' => $discountAmount,
            'used_at' => now(),
        ]);

        DB::table('coupons')
            ->where('id', $coupon->id)
            ->increment('times_used');
    }
}
