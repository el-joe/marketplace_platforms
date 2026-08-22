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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CouponService
{
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

        return $coupon;
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

    public function calculateDiscount(Coupon $coupon, Cart $cart): int
    {
        $items = $cart->items()->with([
            'vendorListing.productVariant.product',
            'adminListing.productVariant.product',
        ])->get()->all();

        $applicableSubtotal = $this->resolveApplicableSubtotal($coupon, (int) $cart->subtotal, $items);

        if ($applicableSubtotal <= 0) {
            return 0;
        }

        $discount = match ($coupon->type) {
            CouponType::Percentage => (int) floor($applicableSubtotal * $coupon->value / 100),
            CouponType::FixedAmount => (int) $coupon->value,
            CouponType::FreeShipping => (int) $cart->estimated_shipping,
            CouponType::Bogo => $this->cheapestQualifyingItemPrice($coupon, $items),
        };

        if ($coupon->max_discount && $discount > $coupon->max_discount) {
            $discount = $coupon->max_discount;
        }

        return max(0, min($discount, $applicableSubtotal));
    }

    /**
     * Sum unit_price × quantity for items matching the coupon's scope.
     */
    private function resolveApplicableSubtotal(Coupon $coupon, int $cartSubtotal, array $items): int
    {
        return match ($coupon->scope) {
            CouponScope::Vendor => $this->sumItems($items, fn ($i) =>
                $i->vendorListing?->vendor_id === $coupon->vendor_id),
            CouponScope::Category => $this->sumItems($items, fn ($i) => $this->itemMatchesCategory($i, $coupon->category_id)),
            CouponScope::Product => $this->sumItems($items, fn ($i) =>
                $i->vendorListing?->vendor_id === $coupon->vendor_id
                && $coupon->products()->where('products.id', $i->vendorListing?->productVariant?->product_id)->exists()),
            default => $cartSubtotal,
        };
    }

    private function sumItems(array $items, \Closure $matcher): int
    {
        $sum = 0;
        foreach ($items as $item) {
            if ($matcher($item)) {
                $sum += (int) $item->unit_price * (int) $item->quantity;
            }
        }
        return $sum;
    }

    /**
     * BOGO: return the unit price of the cheapest qualifying item (free item = cheapest).
     */
    private function cheapestQualifyingItemPrice(Coupon $coupon, array $items): int
    {
        $qualifying = array_filter($items, fn ($i) => match ($coupon->scope) {
            CouponScope::Vendor => $i->vendorListing?->vendor_id === $coupon->vendor_id,
            CouponScope::Category => $this->itemMatchesCategory($i, $coupon->category_id),
            CouponScope::Product => $i->vendorListing?->vendor_id === $coupon->vendor_id
                && $coupon->products()
                ->where('products.id', $i->vendorListing?->productVariant?->product_id)
                ->exists(),
            default => true,
        });

        if (empty($qualifying)) {
            return 0;
        }

        return min(array_map(fn ($i) => (int) $i->unit_price, $qualifying));
    }

    private function itemMatchesCategory(\App\Models\CartItem $item, ?string $couponCategoryId): bool
    {
        if ($couponCategoryId === null) {
            return false;
        }
        $categoryId = $item->vendorListing?->productVariant?->product?->category_id
                    ?? $item->adminListing?->productVariant?->product?->category_id;
        if ($categoryId === null) {
            return false;
        }
        if ($categoryId === $couponCategoryId) {
            return true;
        }
        $cat = \App\Models\Category::find($categoryId);
        return $cat?->ancestors()->where('id', $couponCategoryId)->exists() ?? false;
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
