<?php

namespace App\Services\Checkout;

use App\Enums\CouponScope;
use App\Enums\CouponType;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Country;
use App\Models\Customer;
use App\Models\WarrantyPlan;
use App\Services\WarrantyPlanService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Single source of truth for checkout coupon discount math, warranty
 * selection resolution and per-line/sub-order/order tax computation
 * (enhancement.md P-01).
 *
 * Money is BIGINT base-currency units; never multiply/divide by 100 here
 * except when converting a DECIMAL percentage into a fraction.
 *
 * D1: platform commission is computed on the gross line price before the
 * coupon discount (not implemented here — see commission services); the
 * discount amount produced by this engine is what P-03 needs to work out
 * the vendor-funded coupon share.
 * D2: tax = round((line_subtotal - line_discount) * vat%), applied
 * consistently at line, sub-order and order level (order/sub-order tax is
 * always the sum of line tax, by construction).
 */
class CheckoutPricingEngine
{
    public function __construct(
        private readonly WarrantyPlanService $warrantyPlanService,
    ) {}

    public function calculateTax(int $amountCents, Country $country): int
    {
        if ($amountCents <= 0) {
            return 0;
        }

        return (int) round($amountCents * ((float) $country->vat_rate / 100));
    }

    /**
     * Validate and price a coupon against a set of cart lines. $items may
     * be an array of App\Models\CartItem (with vendorListing/adminListing
     * loaded) or the plain associative-array shape produced by
     * App\Services\CheckoutCalculationService::resolveCartItems().
     *
     * @param  array<int, mixed>  $items
     * @return array{discount: int, error: ?string, type: ?string, allocations: array<string, int>}
     */
    public function applyCoupon(Coupon $coupon, Customer $customer, int $subtotalCents, string $currency, array $items): array
    {
        $error = $this->validateCouponEligibility($coupon, $customer, $subtotalCents, $currency, $items);
        if ($error !== null) {
            return ['discount' => 0, 'error' => $error, 'type' => null, 'allocations' => []];
        }

        $lines = $this->normalizeAll($items);
        $applicableLines = array_filter($lines, fn (array $l) => $this->lineMatchesScope($l, $coupon));
        $applicableSubtotal = array_sum(array_map(fn (array $l) => $l['line_subtotal'], $applicableLines));

        if ($applicableSubtotal <= 0) {
            return ['discount' => 0, 'error' => 'Coupon does not apply to any items in your cart.', 'type' => null, 'allocations' => []];
        }

        $type = $coupon->type instanceof CouponType ? $coupon->type->value : (string) $coupon->type;

        if ($type === 'bogo') {
            $cheapest = null;
            foreach ($applicableLines as $l) {
                if ($cheapest === null || $l['unit_price'] < $cheapest['unit_price']) {
                    $cheapest = $l;
                }
            }

            $discount = $cheapest ? (int) $cheapest['unit_price'] : 0;
            $discount = min($discount, $applicableSubtotal);

            return [
                'discount' => $discount,
                'error' => null,
                'type' => $type,
                'allocations' => $cheapest ? [$cheapest['key'] => $discount] : [],
            ];
        }

        $discount = match ($type) {
            'percentage' => (int) round($applicableSubtotal * ((float) $coupon->value / 100)),
            'fixed_amount' => (int) round((float) $coupon->value),
            'free_shipping' => 0,
            default => 0,
        };

        if ($coupon->max_discount !== null && $discount > $coupon->max_discount) {
            $discount = $coupon->max_discount;
        }

        $discount = min($discount, $applicableSubtotal);

        $weights = [];
        foreach ($applicableLines as $l) {
            $weights[$l['key']] = $l['line_subtotal'];
        }

        $allocations = $this->allocateProRata($discount, $weights);

        return ['discount' => $discount, 'error' => null, 'type' => $type, 'allocations' => $allocations];
    }

    /**
     * Largest-remainder pro-rata allocation of $total across $weights so the
     * allocations sum to exactly $total (P-01 task 2).
     *
     * @param  array<string, int>  $weights
     * @return array<string, int>
     */
    public function allocateProRata(int $total, array $weights): array
    {
        $weights = array_filter($weights, fn ($w) => $w > 0);

        if ($total <= 0 || empty($weights)) {
            return array_fill_keys(array_keys($weights), 0);
        }

        $sumWeights = array_sum($weights);
        $result = [];
        $remainders = [];
        $floorSum = 0;

        foreach ($weights as $key => $weight) {
            $exact = ($total * $weight) / $sumWeights;
            $floor = (int) floor($exact);
            $result[$key] = $floor;
            $remainders[$key] = $exact - $floor;
            $floorSum += $floor;
        }

        $remainderToDistribute = $total - $floorSum;

        $keysByRemainder = array_keys($remainders);
        usort($keysByRemainder, function ($a, $b) use ($remainders) {
            return $remainders[$b] <=> $remainders[$a];
        });

        for ($i = 0; $i < $remainderToDistribute && $i < count($keysByRemainder); $i++) {
            $result[$keysByRemainder[$i]] += 1;
        }

        return $result;
    }

    /**
     * @param  array<int, mixed>  $items
     * @param  array<int|string, array{listing_id?: string, warranty_plan_id: string}>  $warrantySelections  keyed either
     *                                                                                                        by cart item id (Customer\CheckoutCalculationService shape) or numeric index (Api\CheckoutCalculationService shape)
     * @return array{selections: array<string, array{plan: WarrantyPlan, price: int}>, total: int}
     */
    public function resolveWarrantySelections(array $items, array $warrantySelections, Country $country, string $currency): array
    {
        $lines = $this->normalizeAll($items);
        $byKey = [];
        foreach ($lines as $l) {
            $byKey[$l['key']] = $l;
        }

        $selections = [];
        $total = 0;

        foreach ($warrantySelections as $selectionKey => $selection) {
            $warrantyPlanId = is_array($selection) ? ($selection['warranty_plan_id'] ?? null) : $selection;

            $lineKey = is_array($selection) && isset($selection['listing_id'])
                ? $this->keyForListingId($lines, $selection['listing_id'])
                : (string) $selectionKey;

            $line = $byKey[$lineKey] ?? null;
            if (! $line) {
                throw ValidationException::withMessages([
                    'warranty_selections' => "No cart item found for this warranty selection.",
                ]);
            }

            $plan = WarrantyPlan::active()->find($warrantyPlanId);
            if (! $plan) {
                throw ValidationException::withMessages([
                    'warranty_selections' => "Warranty plan {$warrantyPlanId} is not available.",
                ]);
            }

            $product = $line['product'];
            if ($product) {
                $applicablePlanIds = collect($this->warrantyPlanService->getPlansForProduct($product, $country->id, $currency, (int) $line['unit_price']))
                    ->pluck('id')
                    ->all();

                if (! in_array($plan->id, $applicablePlanIds, true)) {
                    throw ValidationException::withMessages([
                        'warranty_selections' => "Warranty plan {$warrantyPlanId} is not applicable to this product.",
                    ]);
                }
            } elseif ($plan->category_id !== null && $plan->category_id !== $line['category_id']) {
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

            $resolvedPrice = $plan->resolvePrice((int) $line['unit_price']);

            $selections[$lineKey] = ['plan' => $plan, 'price' => $resolvedPrice];
            $total += $resolvedPrice;
        }

        return ['selections' => $selections, 'total' => $total];
    }

    /**
     * Price a normalized cart into an immutable PricedCart. Every figure at
     * line/sub-order/order level is derived bottom-up from the same line
     * data, so the sums always reconcile exactly (P-01 acceptance criteria).
     *
     * @param  array<int, mixed>  $items
     * @param  array<string, int>  $couponAllocations  line key => coupon discount amount
     * @param  array<string, int>  $loyaltyAllocations  line key => loyalty discount amount
     * @param  array<string, array{plan: WarrantyPlan, price: int}>  $warrantySelections  line key => resolved warranty
     * @param  array<string, int>  $shippingByGroup  vendor key (or 'platform') => shipping fee for that sub-order
     */
    public function priceCart(
        array $items,
        Country $country,
        int $shippingFeeCents,
        int $codFeeCents,
        int $couponDiscountCents,
        array $couponAllocations,
        int $loyaltyDiscountCents,
        array $loyaltyAllocations,
        int $giftCardAppliedCents,
        array $warrantySelections,
        array $shippingByGroup = [],
    ): PricedCart {
        $lines = $this->normalizeAll($items);

        $pricedLines = [];
        $groups = [];
        $orderSubtotal = 0;
        $orderTax = 0;
        $orderWarrantyTotal = 0;

        foreach ($lines as $l) {
            $key = $l['key'];
            $lineSubtotal = $l['line_subtotal'];
            $lineDiscount = (int) ($couponAllocations[$key] ?? 0);
            $lineLoyalty = (int) ($loyaltyAllocations[$key] ?? 0);
            $taxable = max(0, $lineSubtotal - $lineDiscount - $lineLoyalty);
            $lineTax = $this->calculateTax($taxable, $country);

            $warranty = $warrantySelections[$key] ?? null;
            $warrantyPrice = $warranty['price'] ?? 0;
            $warrantyTax = $this->calculateTax($warrantyPrice, $country);

            $lineTotal = $taxable + $lineTax + $warrantyPrice + $warrantyTax;

            $pricedLines[] = new PricedLine(
                id: $key,
                vendorId: $l['vendor_id'],
                unitPrice: $l['unit_price'],
                quantity: $l['quantity'],
                lineSubtotal: $lineSubtotal,
                lineDiscount: $lineDiscount,
                lineLoyaltyDiscount: $lineLoyalty,
                taxable: $taxable,
                lineTax: $lineTax,
                warrantyPrice: $warrantyPrice,
                warrantyTax: $warrantyTax,
                lineTotal: $lineTotal,
            );

            $groupKey = $l['vendor_id'] ?? 'platform';
            $groups[$groupKey]['subtotal'] = ($groups[$groupKey]['subtotal'] ?? 0) + $lineSubtotal;
            $groups[$groupKey]['discount'] = ($groups[$groupKey]['discount'] ?? 0) + $lineDiscount;
            $groups[$groupKey]['loyalty_discount'] = ($groups[$groupKey]['loyalty_discount'] ?? 0) + $lineLoyalty;
            $groups[$groupKey]['tax'] = ($groups[$groupKey]['tax'] ?? 0) + $lineTax + $warrantyTax;
            $groups[$groupKey]['warranty_total'] = ($groups[$groupKey]['warranty_total'] ?? 0) + $warrantyPrice;

            $orderSubtotal += $lineSubtotal;
            $orderTax += $lineTax + $warrantyTax;
            $orderWarrantyTotal += $warrantyPrice;
        }

        $subOrders = [];
        foreach ($groups as $vendorKey => $g) {
            $subOrders[$vendorKey] = new PricedSubOrder(
                vendorId: $vendorKey === 'platform' ? null : $vendorKey,
                subtotal: $g['subtotal'],
                discount: $g['discount'],
                loyaltyDiscount: $g['loyalty_discount'],
                tax: $g['tax'],
                warrantyTotal: $g['warranty_total'],
                shipping: (int) ($shippingByGroup[$vendorKey] ?? 0),
            );
        }

        $total = max(0, $orderSubtotal
            - $couponDiscountCents
            - $loyaltyDiscountCents
            + $shippingFeeCents
            + $codFeeCents
            + $orderTax
            + $orderWarrantyTotal
            - $giftCardAppliedCents);

        return new PricedCart(
            lines: $pricedLines,
            subOrders: $subOrders,
            subtotal: $orderSubtotal,
            discount: $couponDiscountCents,
            loyaltyDiscount: $loyaltyDiscountCents,
            shipping: $shippingFeeCents,
            codFee: $codFeeCents,
            tax: $orderTax,
            warrantyTotal: $orderWarrantyTotal,
            giftCardApplied: $giftCardAppliedCents,
            total: $total,
            currency: $country->currency_code,
        );
    }

    // ── Coupon eligibility (non-money) ──────────────────────────────────

    private function validateCouponEligibility(Coupon $coupon, Customer $customer, int $subtotalCents, string $currency, array $items): ?string
    {
        if (! $coupon->is_active) {
            return 'Coupon is not active.';
        }

        $now = Carbon::now();
        if (($coupon->valid_from && $now->lt($coupon->valid_from))
            || ($coupon->valid_until && $now->gt($coupon->valid_until))) {
            return 'Coupon is not valid at this time.';
        }

        if ($coupon->currency !== null && $coupon->currency !== $currency) {
            return 'Coupon currency does not match order currency.';
        }

        if ($coupon->min_order_amount !== null && $subtotalCents < $coupon->min_order_amount) {
            return 'Order does not meet the minimum amount for this coupon.';
        }

        if ($coupon->usage_limit_total !== null && $coupon->times_used >= $coupon->usage_limit_total) {
            return 'Coupon usage limit reached.';
        }

        if ($coupon->usage_limit_per_customer !== null) {
            $used = CouponUsage::where('coupon_id', $coupon->id)->where('customer_id', $customer->id)->count();
            if ($used >= $coupon->usage_limit_per_customer) {
                return 'You have already used this coupon the maximum number of times.';
            }
        }

        if ($coupon->max_orders_per_customer_per_month !== null) {
            $usedThisMonth = CouponUsage::where('coupon_id', $coupon->id)
                ->where('customer_id', $customer->id)
                ->where('used_at', '>=', $now->copy()->startOfMonth())
                ->count();
            if ($usedThisMonth >= $coupon->max_orders_per_customer_per_month) {
                return 'Monthly usage limit for this coupon has been reached.';
            }
        }

        if (property_exists($coupon, 'shipping_type_restriction') || $coupon->shipping_type_restriction !== null) {
            if ($coupon->shipping_type_restriction !== null
                && $coupon->shipping_type_restriction !== \App\Enums\CouponShippingTypeRestriction::All) {
                $lines = $this->normalizeAll($items);
                $mismatched = collect($lines)->contains(fn (array $l) => $l['shipping_type'] !== $coupon->shipping_type_restriction->value);

                if ($mismatched) {
                    return 'This coupon is only valid for '.strtoupper($coupon->shipping_type_restriction->value).' shipping orders.';
                }
            }
        }

        return null;
    }

    private function lineMatchesScope(array $line, Coupon $coupon): bool
    {
        $scope = $coupon->scope instanceof CouponScope ? $coupon->scope->value : $coupon->scope;

        return match ($scope) {
            'vendor' => $line['vendor_id'] === $coupon->vendor_id,
            'category' => $line['category_id'] !== null && $this->categoryMatches($line['category_id'], $coupon->category_id),
            'product' => $line['product_id'] !== null
                && $line['vendor_id'] === $coupon->vendor_id
                && $coupon->products()->where('products.id', $line['product_id'])->exists(),
            default => true,
        };
    }

    private function categoryMatches(string $itemCategoryId, ?string $couponCategoryId): bool
    {
        if ($couponCategoryId === null) {
            return false;
        }

        if ($itemCategoryId === $couponCategoryId) {
            return true;
        }

        $category = Category::find($itemCategoryId);

        return $category !== null && $category->ancestors()->where('id', $couponCategoryId)->exists();
    }

    // ── Line normalization: accepts App\Models\CartItem or the plain-array
    //    shape used by App\Services\CheckoutCalculationService ──────────

    /**
     * @return array<int, array{key: string, vendor_id: ?string, category_id: ?string, product_id: ?string, unit_price: int, quantity: int, line_subtotal: int, shipping_type: string, product: mixed}>
     */
    private function normalizeAll(array $items): array
    {
        $lines = [];
        foreach ($items as $index => $item) {
            $lines[] = $this->normalize($item, $index);
        }

        return $lines;
    }

    private function normalize(mixed $item, int|string $index): array
    {
        if (is_array($item)) {
            $listing = $item['listing'] ?? null;
            $variant = $listing?->productVariant;
            $product = $variant?->product;

            return [
                'key' => (string) $index,
                'vendor_id' => $item['is_admin'] ?? false ? null : ($item['vendor_id'] ?? null),
                'category_id' => $product?->category_id,
                'product_id' => $variant?->product_id,
                'unit_price' => (int) $item['unit_price'],
                'quantity' => (int) $item['quantity'],
                'line_subtotal' => (int) $item['unit_price'] * (int) $item['quantity'],
                'shipping_type' => ($item['is_admin'] ?? false) ? 'fbn' : match ($listing?->fulfillment_model ?? null) {
                    'fbn' => 'fbn',
                    'cross_dock' => 'fbp',
                    default => 'fbm',
                },
                'product' => $product,
                'listing_id' => $listing?->id,
            ];
        }

        // App\Models\CartItem (or any object exposing the same shape).
        $listing = $item->vendorListing ?? $item->adminListing ?? null;
        $variant = $listing?->productVariant;
        $product = $variant?->product;

        return [
            'key' => (string) ($item->id ?? $index),
            'vendor_id' => $item->vendorListing?->vendor_id ?? null,
            'category_id' => $product?->category_id,
            'product_id' => $variant?->product_id,
            'unit_price' => (int) $item->unit_price,
            'quantity' => (int) $item->quantity,
            'line_subtotal' => (int) $item->unit_price * (int) $item->quantity,
            'shipping_type' => $item->adminListing !== null ? 'fbn' : match ($item->vendorListing?->fulfillment_model ?? null) {
                'fbn' => 'fbn',
                'cross_dock' => 'fbp',
                default => 'fbm',
            },
            'product' => $product,
            'listing_id' => $listing?->id,
        ];
    }

    private function keyForListingId(array $lines, string $listingId): ?string
    {
        foreach ($lines as $l) {
            if (($l['listing_id'] ?? null) === $listingId) {
                return $l['key'];
            }
        }

        return null;
    }
}
