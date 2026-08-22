<?php

namespace App\Services;

use App\Enums\GlobalSystemType;
use App\Models\Address;
use App\Models\AdminListing;
use App\Models\City;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Country;
use App\Models\Customer;
use App\Models\GiftCard;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use App\Models\VendorListing;
use App\Models\Wallet;
use App\Models\WarrantyPlan;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * All monetary values are BIGINT base-currency units. Never / 100 or * 100.
 */
class CheckoutCalculationService
{
    public function __construct(
        private readonly ShippingCalculationService $shippingCalculationService,
        private readonly ExceptionalZoneService $exceptionalZoneService,
    ) {}

    /**
     * @param  array<int, array{listing_id: string, listing_type: string, quantity: int}>  $cartItems
     * @param  array{address_id: string, payment_method: string, coupon_code?: string, wallet_use?: bool, gift_card_code?: string, warranty_selections?: array}  $options
     */
    public function calculate(Customer $customer, array $cartItems, array $options): array
    {
        $country = Country::findOrFail($customer->country_id);
        $currency = $country->currency_code;

        $address = Address::where('id', $options['address_id'])
            ->where('addressable_type', Customer::class)
            ->where('addressable_id', $customer->id)
            ->first();

        if (! $address) {
            throw ValidationException::withMessages(['address_id' => 'Address not found.']);
        }

        $address->loadMissing('city');

        $items = $this->resolveCartItems($cartItems);
        if (empty($items)) {
            throw ValidationException::withMessages(['cart_items' => 'Cart is empty.']);
        }

        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += $item['unit_price'] * $item['quantity'];
        }

        // Shipping methods are embedded per resolved cart item; group and calculate per method
        $shippingBreakdown = $this->calculateShippingByVendor($items, $address, $country);
        $shippingTotal = (int) collect($shippingBreakdown)->sum('customer_pays');

        $isCod = ($options['payment_method'] ?? null) === 'cod';
        $codFee = 0;
        if ($isCod) {
            if (! $this->codAvailable($address, $country)) {
                throw ValidationException::withMessages(['payment_method' => 'Cash on delivery is not available for this address.']);
            }
            $methodIds = collect($items)->pluck('selected_shipping_method_id')->filter()->unique();
            $codFee = (int) ShippingMethod::whereIn('id', $methodIds)->get()
                ->sum(fn (ShippingMethod $method) => $this->resolveCodFee($address, $method));
        }

        $couponResponse = null;
        $discount = 0;
        if (! empty($options['coupon_code'])) {
            $coupon = Coupon::where('code', $options['coupon_code'])->first();
            if (! $coupon) {
                throw ValidationException::withMessages(['coupon_code' => 'Invalid coupon code.']);
            }

            $couponResult = $this->applyCoupon($coupon, $customer, $subtotal, $currency, $items);
            if ($couponResult['error']) {
                throw ValidationException::withMessages(['coupon_code' => $couponResult['error']]);
            }

            $discount = $couponResult['discount'];
            $couponResponse = [
                'code' => $coupon->code,
                'type' => $coupon->type?->value,
                'value' => $coupon->value,
                'discount' => $discount,
            ];
        }

        $warrantyResult = $this->resolveWarrantySelections($items, $options['warranty_selections'] ?? [], $country, $currency);
        $warrantyTotal = $warrantyResult['total'];

        $taxable = max(0, $subtotal - $discount);
        $tax = (int) round($taxable * ((float) $country->vat_rate / 100));

        $preDeductionTotal = max(0, $subtotal - $discount + $shippingTotal + $codFee + $tax + $warrantyTotal);

        $giftCardBalanceAvailable = 0;
        $giftCardDeduction = 0;
        if (! empty($options['gift_card_code'])) {
            $giftCard = GiftCard::where('code', $options['gift_card_code'])->first();

            if (! $giftCard || $giftCard->status !== 'active') {
                throw ValidationException::withMessages(['gift_card_code' => 'Gift card not found or inactive.']);
            }
            if ($giftCard->expires_at && $giftCard->expires_at->isPast()) {
                throw ValidationException::withMessages(['gift_card_code' => 'Gift card has expired.']);
            }
            if ($giftCard->currency !== $currency) {
                throw ValidationException::withMessages(['gift_card_code' => 'Gift card currency does not match order currency.']);
            }

            $giftCardBalanceAvailable = (int) $giftCard->getRawOriginal('balance');
            $giftCardDeduction = min($giftCardBalanceAvailable, $preDeductionTotal);
        }

        $remainingAfterGiftCard = $preDeductionTotal - $giftCardDeduction;

        $walletBalanceAvailable = 0;
        $walletDeduction = 0;
        if (! empty($options['wallet_use'])) {
            $wallet = Wallet::where('owner_type', 'customer')->where('owner_id', $customer->id)->first();

            if ($wallet) {
                if ($wallet->is_frozen) {
                    throw ValidationException::withMessages(['wallet_use' => 'Wallet is frozen.']);
                }
                if ($wallet->currency !== $currency) {
                    throw ValidationException::withMessages(['wallet_use' => 'Wallet currency does not match order currency.']);
                }

                $walletBalanceAvailable = (int) $wallet->getRawOriginal('balance');
                $walletDeduction = min($walletBalanceAvailable, $remainingAfterGiftCard);
            }
        }

        $total = max(0, $remainingAfterGiftCard - $walletDeduction);

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'shipping_breakdown' => $shippingBreakdown,
            'shipping' => $shippingTotal,
            'cod_fee' => $codFee,
            'tax' => $tax,
            'warranty_total' => $warrantyTotal,
            'wallet_deduction' => $walletDeduction,
            'gift_card_deduction' => $giftCardDeduction,
            'total' => $total,
            'currency' => $currency,
            'coupon' => $couponResponse,
            'wallet_balance_available' => $walletBalanceAvailable,
            'gift_card_balance_available' => $giftCardBalanceAvailable,
        ];
    }

    public function codAvailable(Address $address, Country $country): bool
    {
        $address->loadMissing('city');

        return (bool) ($address->city?->cod_available && $country->cod_available);
    }

    /**
     * @return array<int, array{vendor_listing_id: ?string, admin_listing_id: ?string, vendor_id: ?string, product_variant_id: string, quantity: int, unit_price: int, weight_grams: int, listing: mixed, is_admin: bool}>
     */
    public function resolveCartItems(array $cartItems): array
    {
        $resolved = [];

        foreach ($cartItems as $index => $cartItem) {
            $quantity = (int) ($cartItem['quantity'] ?? 0);
            if ($quantity < 1) {
                throw ValidationException::withMessages(["cart_items.$index.quantity" => 'Quantity must be at least 1.']);
            }

            $isAdmin = ($cartItem['listing_type'] ?? null) === 'admin';

            if ($isAdmin) {
                $listing = AdminListing::with('productVariant')->find($cartItem['listing_id']);
                if (! $listing) {
                    throw ValidationException::withMessages(["cart_items.$index.listing_id" => 'Listing not found.']);
                }

                $resolved[] = [
                    'vendor_listing_id' => null,
                    'admin_listing_id' => $listing->id,
                    'vendor_id' => null,
                    'product_variant_id' => $listing->product_variant_id,
                    'quantity' => $quantity,
                    'unit_price' => (int) $listing->getRawOriginal('price'),
                    'weight_grams' => (int) ($listing->productVariant?->weight_grams ?? 0),
                    'listing' => $listing,
                    'is_admin' => true,
                    'selected_shipping_method_id' => $cartItem['selected_shipping_method_id'] ?? null,
                ];

                continue;
            }

            $listing = VendorListing::with('productVariant')->find($cartItem['listing_id']);
            if (! $listing) {
                throw ValidationException::withMessages(["cart_items.$index.listing_id" => 'Listing not found.']);
            }

            $resolved[] = [
                'vendor_listing_id' => $listing->id,
                'admin_listing_id' => null,
                'vendor_id' => $listing->vendor_id,
                'product_variant_id' => $listing->product_variant_id,
                'quantity' => $quantity,
                'unit_price' => (int) $listing->price,
                'weight_grams' => (int) ($listing->productVariant?->weight_grams ?? 0),
                'listing' => $listing,
                'is_admin' => false,
                'selected_shipping_method_id' => $cartItem['selected_shipping_method_id']
                    ?? $listing->primary_shipping_method_id
                    ?? null,
            ];
        }

        return $resolved;
    }

    /**
     * @param  array  $items  resolved cart items (see resolveCartItems)
     * @return array<string, array{customer_pays: int, carrier_cost: int, gap: int, vendor_contribution: int, admin_subsidy: int, billable_weight_grams: int, vendor_id: ?string, is_admin: bool}>
     */
    public function calculateShippingByVendor(array $items, Address $address, Country $country): array
    {
        $city = City::find($address->city_id);
        $zoneId = $city?->shipping_zone_id;

        $groups = [];
        foreach ($items as $item) {
            $key = $item['is_admin'] ? 'admin' : $item['vendor_id'];
            $groups[$key][] = $item;
        }

        $breakdown = [];

        foreach ($groups as $key => $groupItems) {
            if ($key === 'admin') {
                $fee = (int) collect($groupItems)->sum(fn ($i) => (int) ($i['listing']->getRawOriginal('shipping_cost') ?? 0) * $i['quantity']);

                $breakdown['admin'] = [
                    'customer_pays' => $fee,
                    'carrier_cost' => $fee,
                    'gap' => 0,
                    'vendor_contribution' => 0,
                    'admin_subsidy' => 0,
                    'billable_weight_grams' => (int) collect($groupItems)->sum(fn ($i) => $i['weight_grams'] * $i['quantity']),
                    'vendor_id' => null,
                    'is_admin' => true,
                ];

                continue;
            }

            if (! $zoneId) {
                $breakdown[$key] = [
                    'customer_pays' => 0,
                    'carrier_cost' => 0,
                    'gap' => 0,
                    'vendor_contribution' => 0,
                    'admin_subsidy' => 0,
                    'billable_weight_grams' => 0,
                    'vendor_id' => $key,
                    'is_admin' => false,
                ];

                continue;
            }

            $zone = \App\Models\ShippingZone::find($zoneId);
            $firstItem = $groupItems[0];
            $listing = $firstItem['listing'];
            $vendor = $listing->vendor;
            $totalWeightGrams = (int) collect($groupItems)->sum(fn ($i) => $i['weight_grams'] * $i['quantity']);

            $selectedMethodId = $firstItem['selected_shipping_method_id']
                ?? $listing->primary_shipping_method_id
                ?? null;
            $method = $selectedMethodId ? ShippingMethod::find($selectedMethodId) : null;

            if (! $method) {
                $breakdown[$key] = [
                    'customer_pays' => 0,
                    'carrier_cost' => 0,
                    'gap' => 0,
                    'vendor_contribution' => 0,
                    'admin_subsidy' => 0,
                    'billable_weight_grams' => $totalWeightGrams,
                    'vendor_id' => $key,
                    'is_admin' => false,
                ];

                continue;
            }

            $result = $this->shippingCalculationService->calculate(
                vendor: $vendor,
                listing: $listing,
                quantity: (int) collect($groupItems)->sum('quantity'),
                destinationZone: $zone,
                method: $method,
                billableWeightGrams: $totalWeightGrams,
            );

            // Exceptional zone check: completely separate from the normal
            // shipping zone rate. If the customer's city has an accepted
            // exceptional zone alert for this vendor+warehouse and the
            // reported carrier fee exceeds what the customer pays, split
            // the gap between vendor and admin.
            $exceptionalResult = $city
                ? $this->exceptionalZoneService->resolve(
                    listing: $listing,
                    city: $city,
                    normalShippingFee: $result->customerPays,
                )
                : null;

            $breakdown[$key] = [
                'customer_pays' => $result->customerPays,
                'carrier_cost' => $exceptionalResult['reported_carrier_fee'] ?? $result->customerPays,
                'gap' => $exceptionalResult['gap'] ?? 0,
                'vendor_contribution' => $exceptionalResult['vendor_contribution'] ?? 0,
                'admin_subsidy' => $exceptionalResult['admin_subsidy'] ?? 0,
                'exceptional_zone_subsidy_id' => $exceptionalResult['subsidy_rule_id'] ?? null,
                'billable_weight_grams' => $result->billableWeightGrams,
                'vendor_id' => $key,
                'is_admin' => false,
            ];
        }

        return $breakdown;
    }

    private function resolveCodFee(Address $address, ShippingMethod $shippingMethod): int
    {
        $city = City::find($address->city_id);
        if (! $city || ! $city->shipping_zone_id) {
            return 0;
        }

        $rate = ShippingRate::where('destination_zone_id', $city->shipping_zone_id)
            ->where('shipping_method_id', $shippingMethod->id)
            ->where('is_active', 1)
            ->orderBy('base_fee')
            ->first();

        return (int) ($rate->cod_extra_fee ?? 0);
    }

    public function applyCoupon(Coupon $coupon, Customer $customer, int $subtotalCents, string $currency, array $items): array
    {
        if (! $coupon->is_active) {
            return ['discount' => 0, 'error' => 'Coupon is not active.'];
        }

        $now = Carbon::now();
        if (($coupon->valid_from && $now->lt($coupon->valid_from))
            || ($coupon->valid_until && $now->gt($coupon->valid_until))) {
            return ['discount' => 0, 'error' => 'Coupon is not valid at this time.'];
        }

        if ($coupon->currency !== null && $coupon->currency !== $currency) {
            return ['discount' => 0, 'error' => 'Coupon currency does not match order currency.'];
        }

        if ($coupon->min_order_amount !== null && $subtotalCents < $coupon->min_order_amount) {
            return ['discount' => 0, 'error' => 'Order does not meet the minimum amount for this coupon.'];
        }

        if ($coupon->usage_limit_total !== null && $coupon->times_used >= $coupon->usage_limit_total) {
            return ['discount' => 0, 'error' => 'Coupon usage limit reached.'];
        }

        if ($coupon->usage_limit_per_customer !== null) {
            $used = CouponUsage::where('coupon_id', $coupon->id)->where('customer_id', $customer->id)->count();
            if ($used >= $coupon->usage_limit_per_customer) {
                return ['discount' => 0, 'error' => 'You have already used this coupon the maximum number of times.'];
            }
        }

        if ($coupon->max_orders_per_customer_per_month !== null) {
            $usedThisMonth = CouponUsage::where('coupon_id', $coupon->id)
                ->where('customer_id', $customer->id)
                ->where('used_at', '>=', $now->copy()->startOfMonth())
                ->count();
            if ($usedThisMonth >= $coupon->max_orders_per_customer_per_month) {
                return ['discount' => 0, 'error' => 'Monthly usage limit for this coupon has been reached.'];
            }
        }

        $applicableSubtotal = $this->resolveApplicableSubtotal($coupon, $subtotalCents, $items);
        if ($applicableSubtotal <= 0) {
            return ['discount' => 0, 'error' => 'Coupon does not apply to any items in your cart.'];
        }

        $discount = match ($coupon->type?->value) {
            'percentage' => (int) round($applicableSubtotal * ((float) $coupon->value / 100)),
            'fixed_amount' => (int) round((float) $coupon->value),
            'free_shipping' => 0,
            'bogo' => $this->cheapestQualifyingItemPrice($coupon, $items),
            default => 0,
        };

        if ($coupon->max_discount !== null && $discount > $coupon->max_discount) {
            $discount = $coupon->max_discount;
        }

        $discount = min($discount, $applicableSubtotal);

        return ['discount' => $discount, 'error' => null];
    }

    private function resolveApplicableSubtotal(Coupon $coupon, int $subtotalCents, array $items): int
    {
        return match ($coupon->scope?->value) {
            'vendor' => $this->sumItems($items, fn ($i) => $i['vendor_id'] === $coupon->vendor_id),
            'category' => $this->sumItems($items, function ($i) use ($coupon) {
                $categoryId = $i['listing']->productVariant?->product?->category_id ?? null;

                return $categoryId !== null && $categoryId === $coupon->category_id;
            }),
            'product' => $this->sumItems($items, function ($i) use ($coupon) {
                $productId = $i['listing']->productVariant?->product_id ?? null;

                return $productId !== null && $coupon->products()->where('products.id', $productId)->exists();
            }),
            default => $subtotalCents,
        };
    }

    private function sumItems(array $items, \Closure $matcher): int
    {
        $sum = 0;
        foreach ($items as $item) {
            if ($matcher($item)) {
                $sum += $item['unit_price'] * $item['quantity'];
            }
        }

        return $sum;
    }

    private function cheapestQualifyingItemPrice(Coupon $coupon, array $items): int
    {
        $applicable = array_filter($items, function ($i) use ($coupon) {
            return match ($coupon->scope?->value) {
                'vendor' => $i['vendor_id'] === $coupon->vendor_id,
                'category' => ($i['listing']->productVariant?->product?->category_id ?? null) === $coupon->category_id,
                'product' => $coupon->products()->where('products.id', $i['listing']->productVariant?->product_id)->exists(),
                default => true,
            };
        });

        if (empty($applicable)) {
            return 0;
        }

        return min(array_map(fn ($i) => (int) $i['unit_price'], $applicable));
    }

    /**
     * @param  array<int, array>  $warrantySelections  [cart_item_index => warranty_plan_id]
     * @return array{selections: array<int, array{plan: WarrantyPlan, price: int}>, total: int}
     */
    public function resolveWarrantySelections(array $items, array $warrantySelections, Country $country, string $currency): array
    {
        $selections = [];
        $total = 0;

        foreach ($warrantySelections as $itemIndex => $planId) {
            if (! isset($items[$itemIndex])) {
                throw ValidationException::withMessages(["warranty_selections.$itemIndex" => 'No cart item found for this selection.']);
            }

            $plan = WarrantyPlan::active()->find($planId);
            if (! $plan) {
                throw ValidationException::withMessages(["warranty_selections.$itemIndex" => 'Warranty plan is not available.']);
            }

            $categoryId = $items[$itemIndex]['listing']->productVariant?->product?->category_id ?? null;
            if ($plan->category_id !== null && $plan->category_id !== $categoryId) {
                throw ValidationException::withMessages(["warranty_selections.$itemIndex" => 'Warranty plan is not applicable to this product.']);
            }

            if ($plan->country_ids !== null && ! in_array($country->id, $plan->country_ids, true)) {
                throw ValidationException::withMessages(["warranty_selections.$itemIndex" => 'Warranty plan is not available in your country.']);
            }

            if ($plan->currency !== $currency) {
                throw ValidationException::withMessages(["warranty_selections.$itemIndex" => 'Warranty plan currency does not match order currency.']);
            }

            $selections[$itemIndex] = ['plan' => $plan, 'price' => (int) $plan->price];
            $total += (int) $plan->price;
        }

        return ['selections' => $selections, 'total' => $total];
    }
}
