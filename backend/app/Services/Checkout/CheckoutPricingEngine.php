<?php

namespace App\Services\Checkout;

use App\Enums\CouponCustomerEligibility;
use App\Enums\CouponScope;
use App\Enums\CouponShippingTypeRestriction;
use App\Enums\CouponType;
use App\Enums\OrderStatus;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Commission;
use App\Models\Country;
use App\Models\CountryCategory;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Vendor;
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
     * @return array{discount: int, error: ?string, type: ?string, allocations: array<string, int>, free_shipping_seller_parties: array<int, string>, max_discount: ?int, funded_by: ?string}
     */
    public function applyCoupon(
        Coupon $coupon,
        ?Customer $customer,
        int $subtotalCents,
        string $currency,
        array $items,
        ?string $countryId = null,
        bool $stackedWithOtherDiscount = false,
    ): array {
        $empty = ['discount' => 0, 'error' => null, 'type' => null, 'allocations' => [], 'free_shipping_seller_parties' => [], 'max_discount' => $coupon->max_discount, 'funded_by' => $coupon->funded_by];

        $error = $this->validateCouponEligibility($coupon, $customer, $subtotalCents, $currency, $items, $countryId, $stackedWithOtherDiscount);
        if ($error !== null) {
            return array_merge($empty, ['error' => $error]);
        }

        $lines = $this->normalizeAll($items);
        $applicableLines = array_filter($lines, fn (array $l) => $this->lineMatchesScope($l, $coupon));
        $applicableSubtotal = array_sum(array_map(fn (array $l) => $l['line_subtotal'], $applicableLines));

        if ($applicableSubtotal <= 0) {
            return array_merge($empty, ['error' => __('common.exceptions.checkout.coupon.no_applicable_items')]);
        }

        $inScopeSellerParties = array_values(array_unique(array_map(
            fn (array $l) => $l['vendor_id'] ?? 'platform',
            $applicableLines
        )));

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

            return array_merge($empty, [
                'discount' => $discount,
                'type' => $type,
                'allocations' => $cheapest ? [$cheapest['key'] => $discount] : [],
            ]);
        }

        if ($type === 'free_shipping') {
            return array_merge($empty, [
                'discount' => 0,
                'type' => $type,
                'free_shipping_seller_parties' => $inScopeSellerParties,
            ]);
        }

        $discount = match ($type) {
            'percentage' => (int) round($applicableSubtotal * ((float) $coupon->value / 100)),
            'fixed_amount' => (int) round((float) $coupon->value),
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

        return array_merge($empty, ['discount' => $discount, 'type' => $type, 'allocations' => $allocations]);
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
     *                                                                                                       by cart item id (Customer\CheckoutCalculationService shape) or numeric index (Api\CheckoutCalculationService shape)
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
                    'warranty_selections' => 'No cart item found for this warranty selection.',
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
        int $customsDutyCents = 0,
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

            // Kept consistent with what CheckoutController::placeOrder() persists to
            // order_items.line_total (line_subtotal - line_discount + line_tax); loyalty
            // and warranty are intentionally NOT folded in here even though they are
            // computed above — they are surfaced separately (lineLoyaltyDiscount,
            // warrantyPrice/warrantyTax, and the order/sub-order level totals) so the
            // frontend can still show them, without this specific field diverging from
            // the persisted value (see docs/formula.md §1).
            $lineTotal = $lineSubtotal - $lineDiscount + $lineTax;

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
            + $customsDutyCents
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
            customsDuty: $customsDutyCents,
        );
    }

    /**
     * The vendor/platform/marketer/shipping money split (enhancement.md
     * P-03), computed per line and rolled up per sub-order group. Operates
     * on the same normalized lines priceCart() uses, plus the extra
     * context priceCart() doesn't need: the coupon (for D1's vendor-funded
     * share), the selected gateway's fee config (D4), and the per-group
     * shipping/subsidy/carrier figures already resolved elsewhere in
     * checkout.
     *
     * D4: fee_fixed is charged once per ORDER (not per sub-order) — computed
     * once here as part of $gatewayFeeTotal, then split pro-rata across
     * every sub-order (vendor or platform) by that sub-order's share of
     * $amountDueGatewayCents, via the same largest-remainder allocator
     * priceCart() uses for coupons. That is how a 3-vendor card order ends
     * up paying fee_fixed exactly once in total.
     *
     * @param  array<int, mixed>  $items  same shape priceCart() accepts
     * @param  array<string, int>  $couponAllocations  line key => coupon discount (from applyCoupon())
     * @param  array<string, int>  $vendorContributionByGroup  vendor key (or 'platform') => vendor_contribution_amount
     * @param  array<string, int>  $adminSubsidyByGroup  vendor key (or 'platform') => admin_subsidy_amount
     * @param  array<string, int>  $carrierRawFeeByGroup  vendor key (or 'platform') => the shipping resolver's raw (pre-subsidy) carrier fee for that group; falls back to the group's charged shipping when no resolver ran (e.g. FBN/admin — see enhancement.md P-03 task 3 note)
     * @param  array<string, int>  $chargedShippingByGroup  vendor key (or 'platform') => shipping fee charged to the customer for that group (same values priceCart() was given as $shippingByGroup)
     * @return array{
     *   lines: array<string, array{vendor_coupon_cost:int, platform_coupon_cost:int, platform_commission_after_discount:int, marketer_commission:int}>,
     *   sub_orders: array<string, array{
     *     vendor_coupon_cost:int, platform_coupon_cost:int, platform_commission:int, platform_commission_after_discount:int,
     *     marketer_commission:int, marketer_commission_owner:?string, warranty_revenue:int, gateway_fee:int,
     *     carrier_shipping_cost:int, shipping_gap:int, vendor_payout:int
     *   }>,
     *   gateway_fee_total:int, marketer_commission_total:int, carrier_cost_covered_total:int, platform_net:int, tax_total:int
     * }
     */
    public function computeMoneySplit(
        array $items,
        Country $country,
        ?Coupon $coupon,
        array $couponAllocations,
        array $vendorContributionByGroup,
        array $adminSubsidyByGroup,
        array $carrierRawFeeByGroup,
        array $chargedShippingByGroup,
        float $gatewayFeePct,
        int $gatewayFeeFixed,
        int $amountDueGatewayCents,
        bool $isCod,
        int $codFeeCents,
        int $warrantyTotalCents,
    ): array {
        $lines = $this->normalizeAll($items);

        $vendorSharePct = 0;
        if ($coupon !== null) {
            $vendorSharePct = match ((string) ($coupon->funded_by ?? 'platform')) {
                'vendor' => 100,
                'shared' => (int) ($coupon->vendor_share_pct ?? 0),
                default => 0,
            };
        }

        $lineResults = [];
        $groups = [];

        foreach ($lines as $l) {
            $key = $l['key'];
            $groupKey = $l['vendor_id'] ?? 'platform';
            $isPlatformLine = $l['vendor_id'] === null;

            $lineDiscount = (int) ($couponAllocations[$key] ?? 0);
            $vendorCouponCost = (int) round($lineDiscount * $vendorSharePct / 100);
            $platformCouponCost = $lineDiscount - $vendorCouponCost;

            if ($isPlatformLine) {
                // The platform is the seller for admin-listing lines — there
                // is no vendor payout to compute, the full gross is the
                // platform's own "commission" (enhancement.md P-03: a
                // documented design decision, see CheckoutController).
                $rawCommission = $l['line_subtotal'];
                $commissionCategoryId = null;
            } else {
                $vendor = Vendor::find($l['vendor_id']);
                $base = $l['commission_base_unit_price'] * $l['quantity']; // D1 + marketer-listing fix: gross, before coupon, vendor listing price
                [$rawCommission, $commissionCategoryId] = $this->resolveCommission($vendor, $l['category_id'], $l['fulfillment_model'], $country, $base, $l['quantity']);
            }

            // Marketer commission (D5): owner pays; base is the vendor
            // listing price, not the marketer's resale price.
            $marketerCommission = 0;
            if ($l['is_marketer'] && $l['marketer_commission_type'] === 'fixed') {
                $marketerCommission = $l['marketer_commission_raw'] * $l['quantity'];
            }

            $groups[$groupKey]['vendor_id'] = $isPlatformLine ? null : $l['vendor_id'];
            $groups[$groupKey]['gross'] = ($groups[$groupKey]['gross'] ?? 0) + $l['line_subtotal'];
            $groups[$groupKey]['discount'] = ($groups[$groupKey]['discount'] ?? 0) + $lineDiscount;
            $groups[$groupKey]['vendor_coupon_cost'] = ($groups[$groupKey]['vendor_coupon_cost'] ?? 0) + $vendorCouponCost;
            $groups[$groupKey]['platform_coupon_cost'] = ($groups[$groupKey]['platform_coupon_cost'] ?? 0) + $platformCouponCost;
            $groups[$groupKey]['raw_commission'] = ($groups[$groupKey]['raw_commission'] ?? 0) + $rawCommission;
            $groups[$groupKey]['marketer_commission'] = ($groups[$groupKey]['marketer_commission'] ?? 0) + $marketerCommission;
            $groups[$groupKey]['marketer_owner'] = $l['marketer_owner'] ?? ($groups[$groupKey]['marketer_owner'] ?? null);
            $groups[$groupKey]['lines'][$key] = [
                'vendor_coupon_cost' => $vendorCouponCost,
                'platform_coupon_cost' => $platformCouponCost,
                'raw_commission' => $rawCommission,
                'marketer_commission' => $marketerCommission,
            ];

            $lineResults[$key] = [
                'group_key' => $groupKey,
                'vendor_coupon_cost' => $vendorCouponCost,
                'platform_coupon_cost' => $platformCouponCost,
                'raw_commission' => $rawCommission,
                'marketer_commission' => $marketerCommission,
                'commission_category_id' => $commissionCategoryId,
            ];
        }

        // ── Gateway fee: computed once for the whole order (D4), then split
        //    pro-rata across every group by its share of the card-paid
        //    amount. fee_fixed is included exactly once in the total below.
        $gatewayFeeTotal = $isCod || $amountDueGatewayCents <= 0
            ? 0
            : (int) floor($amountDueGatewayCents * $gatewayFeePct / 100) + $gatewayFeeFixed;

        $groupGrossWeights = [];
        foreach ($groups as $groupKey => $g) {
            $groupGrossWeights[$groupKey] = max(0, $g['gross']);
        }
        $gatewayFeeByGroup = $this->allocateProRata($gatewayFeeTotal, $groupGrossWeights);

        $platformNet = 0;
        $marketerCommissionTotal = 0;
        $carrierCostCoveredTotal = 0;
        $subOrders = [];

        foreach ($groups as $groupKey => $g) {
            $isPlatformGroup = $groupKey === 'platform';

            $platformCommissionAfterDiscount = $g['raw_commission'];
            if (! $isPlatformGroup && $g['vendor_id'] !== null) {
                $vendor = Vendor::find($g['vendor_id']);
                $platformCommissionAfterDiscount = $vendor?->applyCommissionDiscount($g['raw_commission']) ?? $g['raw_commission'];
            }

            $gatewayFee = (int) ($gatewayFeeByGroup[$groupKey] ?? 0);
            $vendorContribution = (int) ($vendorContributionByGroup[$groupKey] ?? 0);
            $adminSubsidy = (int) ($adminSubsidyByGroup[$groupKey] ?? 0);
            $chargedShipping = (int) ($chargedShippingByGroup[$groupKey] ?? 0);
            $carrierShippingCost = (int) ($carrierRawFeeByGroup[$groupKey] ?? $chargedShipping);
            $shippingGap = max(0, $carrierShippingCost - $chargedShipping - $vendorContribution - $adminSubsidy);
            // shipping_revenue = shipping_charged - carrier_cost (see
            // enhancement.md P-03 report for the full derivation of why
            // vendor_contribution/admin_subsidy are added back into
            // platform_net separately rather than folded in here).
            $shippingRevenue = $chargedShipping - $carrierShippingCost;

            $marketerOwner = $g['marketer_owner'];
            $marketerCommission = $g['marketer_commission'];
            $marketerCommissionTotal += $marketerCommission;
            $carrierCostCoveredTotal += $carrierShippingCost;

            if ($isPlatformGroup) {
                $vendorPayout = 0;
            } else {
                $vendorPayout = $g['gross']
                    - $g['vendor_coupon_cost']
                    - $platformCommissionAfterDiscount
                    - $gatewayFee
                    - $vendorContribution
                    - ($marketerOwner === 'vendor' ? $marketerCommission : 0);
            }

            // cod_fee and warranty_revenue are order-level (added once,
            // after the loop below) — they are not per-group here. The
            // gateway fee is only subtracted from platform_net for the
            // platform's OWN sub-orders (admin-listing groups) — a vendor
            // group's gateway_fee share is already paid by that vendor via
            // vendor_payout above, so subtracting it again here would
            // double-count it (D4: "gateway_fee(platform share)").
            $platformNet += $platformCommissionAfterDiscount
                + $shippingRevenue
                - $g['platform_coupon_cost']
                - $adminSubsidy
                - ($marketerOwner === 'platform' ? $marketerCommission : 0)
                - ($isPlatformGroup ? $gatewayFee : 0);

            // Allocate the group's after-discount commission back across its
            // lines (weighted by each line's raw commission), so
            // Sigma(order_items.platform_commission_after_discount) ==
            // sub_orders.platform_commission_after_discount exactly.
            $lineCommissionWeights = array_map(fn ($l) => $l['raw_commission'], $g['lines']);
            $lineCommissionAfterDiscount = $this->allocateProRata($platformCommissionAfterDiscount, $lineCommissionWeights);
            $groupLines = $g['lines'];
            foreach ($groupLines as $lineKey => $lineData) {
                $groupLines[$lineKey]['platform_commission_after_discount'] = $lineCommissionAfterDiscount[$lineKey] ?? 0;
            }

            $subOrders[$groupKey] = [
                'vendor_id' => $g['vendor_id'],
                'vendor_coupon_cost' => $g['vendor_coupon_cost'],
                'platform_coupon_cost' => $g['platform_coupon_cost'],
                'platform_commission' => $g['raw_commission'],
                'platform_commission_after_discount' => $platformCommissionAfterDiscount,
                'marketer_commission' => $marketerCommission,
                'marketer_commission_owner' => $marketerOwner,
                'gateway_fee' => $gatewayFee,
                'carrier_shipping_cost' => $carrierShippingCost,
                'shipping_gap' => $shippingGap,
                'vendor_payout' => $vendorPayout,
                'lines' => $groupLines,
            ];
        }

        // cod_fee and warranty revenue are order-level, credited once here
        // (not per sub-order group, to avoid double counting when there are
        // several vendor groups). Warranty is 100% underwritten by the
        // platform (documented simplification — D3's coverage-start timing
        // is not implemented here, only revenue attribution).
        $platformNet += $codFeeCents + $warrantyTotalCents;

        return [
            'sub_orders' => $subOrders,
            'gateway_fee_total' => $gatewayFeeTotal,
            'marketer_commission_total' => $marketerCommissionTotal,
            'carrier_cost_covered_total' => $carrierCostCoveredTotal,
            'platform_net' => $platformNet,
            'warranty_revenue_total' => $warrantyTotalCents,
        ];
    }

    /**
     * Resolve platform commission for one vendor line: commissions table
     * (vendor+category, vendor-only or category-only rows, highest
     * priority/most specific wins) → vendors.commission_rate override (only
     * when > 0 — the column is NOT NULL DEFAULT 0, so 0 means "no
     * vendor-level override" here) → country_category → category chain
     * (walking ancestors), exactly the precedence enhancement.md P-03 task 1
     * asks for. FBN/FBP is read from the sub-order's own fulfillment_model
     * (never global_system_type — that mismatch was the bug).
     *
     * @return array{0: int, 1: ?string} [commission amount, category id used]
     */
    private function resolveCommission(?Vendor $vendor, ?string $categoryId, string $fulfillmentModel, Country $country, int $baseAmountCents, int $quantity = 1): array
    {
        $today = Carbon::today()->toDateString();

        $categoryChainIds = [];
        $category = $categoryId ? Category::find($categoryId) : null;
        $walker = $category;
        $levels = 0;
        while ($walker !== null && $levels < 6) {
            $categoryChainIds[] = $walker->id;
            $walker = $walker->parent;
            $levels++;
        }

        if ($vendor !== null || ! empty($categoryChainIds)) {
            $rows = Commission::query()
                ->where(function ($q) use ($vendor) {
                    $q->whereNull('vendor_id');
                    if ($vendor !== null) {
                        $q->orWhere('vendor_id', $vendor->id);
                    }
                })
                ->where(function ($q) use ($categoryChainIds) {
                    $q->whereNull('category_id');
                    if (! empty($categoryChainIds)) {
                        $q->orWhereIn('category_id', $categoryChainIds);
                    }
                })
                ->where('effective_from', '<=', $today)
                ->where(function ($q) use ($today) {
                    $q->whereNull('effective_until')->orWhere('effective_until', '>=', $today);
                })
                ->get();

            $best = null;
            $bestScore = -1;
            $bestCategoryDepth = PHP_INT_MAX;
            foreach ($rows as $row) {
                $isVendorSpecific = $vendor !== null && $row->vendor_id === $vendor->id;
                $categoryDepth = $row->category_id ? array_search($row->category_id, $categoryChainIds, true) : null;
                $isCategorySpecific = $categoryDepth !== false && $categoryDepth !== null;

                $score = ($isVendorSpecific ? 2 : 0) + ($isCategorySpecific ? 1 : 0);
                $depth = $isCategorySpecific ? $categoryDepth : PHP_INT_MAX;

                if ($score > $bestScore
                    || ($score === $bestScore && $depth < $bestCategoryDepth)
                    || ($score === $bestScore && $depth === $bestCategoryDepth && $best !== null && $row->priority > $best->priority)) {
                    $best = $row;
                    $bestScore = $score;
                    $bestCategoryDepth = $depth;
                }
            }

            if ($best !== null) {
                $amount = (int) round($baseAmountCents * ((float) $best->rate_pct) / 100);
                $amount = max($amount, (int) $best->min_commission);
                if ($best->max_commission !== null) {
                    $amount = min($amount, (int) $best->max_commission);
                }

                return [$amount, $best->category_id];
            }
        }

        if ($vendor !== null && (float) $vendor->commission_rate > 0) {
            return [(int) round($baseAmountCents * ((float) $vendor->commission_rate) / 100), $categoryId];
        }

        // Category chain fallback (FBN/FBP-specific pct/fixed on the
        // category itself, walking up to the nearest ancestor that has one),
        // optionally overridden per-country via country_categories.
        $isFBN = $fulfillmentModel === 'fbn';
        $resolved = $category;
        $walker = $category;
        $levels = 0;
        while ($walker !== null && $levels < 6) {
            $pct = (float) ($isFBN ? $walker->commission_fbn_pct : $walker->commission_fbp_pct);
            $fixed = (int) ($isFBN ? $walker->commission_fbn_fixed : $walker->commission_fbp_fixed);
            if ($pct > 0 || $fixed > 0) {
                $resolved = $walker;
                break;
            }
            $walker = $walker->parent;
            $levels++;
        }

        if ($resolved === null) {
            return [0, null];
        }

        $countryCategory = CountryCategory::where('country_id', $country->id)
            ->where('category_id', $resolved->id)
            ->first();

        $pct = (float) ($isFBN
            ? ($countryCategory?->commission_fbn_pct ?? $resolved->commission_fbn_pct)
            : ($countryCategory?->commission_fbp_pct ?? $resolved->commission_fbp_pct)) ?: 0.0;
        $fixed = (int) ($isFBN
            ? ($countryCategory?->commission_fbn_fixed ?? $resolved->commission_fbn_fixed)
            : ($countryCategory?->commission_fbp_fixed ?? $resolved->commission_fbp_fixed)) ?: 0;

        $amount = (int) floor($baseAmountCents * $pct / 100) + $fixed * $quantity;

        return [$amount, $resolved->id];
    }

    // ── Coupon eligibility (non-money) ──────────────────────────────────

    private function validateCouponEligibility(
        Coupon $coupon,
        ?Customer $customer,
        int $subtotalCents,
        string $currency,
        array $items,
        ?string $countryId = null,
        bool $stackedWithOtherDiscount = false,
    ): ?string {
        if (! $coupon->is_active) {
            return __('common.exceptions.checkout.coupon.not_active');
        }

        $now = Carbon::now();
        if (($coupon->valid_from && $now->lt($coupon->valid_from))
            || ($coupon->valid_until && $now->gt($coupon->valid_until))) {
            return __('common.exceptions.checkout.coupon.not_valid_now');
        }

        if ($countryId !== null && $coupon->country_ids !== null && ! in_array($countryId, $coupon->country_ids, true)) {
            return __('common.exceptions.checkout.coupon.country_not_eligible');
        }

        if ($coupon->currency !== null && $coupon->currency !== $currency) {
            return __('common.exceptions.checkout.coupon.currency_mismatch');
        }

        if ($coupon->min_order_amount !== null && $subtotalCents < $coupon->min_order_amount) {
            return __('common.exceptions.checkout.coupon.min_order_not_reached');
        }

        if ($stackedWithOtherDiscount && ! $coupon->is_stackable) {
            return __('common.exceptions.checkout.coupon.not_stackable');
        }

        $eligibility = $coupon->customer_eligibility instanceof CouponCustomerEligibility
            ? $coupon->customer_eligibility->value
            : (string) $coupon->customer_eligibility;

        // Coupons are customer-identity-aware by design: customer_eligibility
        // ('new_customers'/'specific_users'/'specific_segment') and
        // usage_limit_per_customer / max_orders_per_customer_per_month all
        // require a stable customer identity to enforce, and every coupon row
        // in this schema carries a (non-nullable, default 1) per-customer
        // usage limit. A guest cart (session_token, no customer_id) has no
        // identity CouponUsage can be tied to, so its usage could never be
        // tracked or capped reliably across sessions/devices. Product
        // decision: coupons require a logged-in account; guests are rejected
        // here with a clear, actionable error instead of silently computing
        // (or failing to compute) a discount.
        if ($customer === null) {
            return __('common.exceptions.checkout.coupon.account_required');
        }

        if ($eligibility === 'new_customers') {
            $hasCompletedOrder = Order::where('customer_id', $customer->id)
                ->where('status', OrderStatus::Completed)
                ->exists();
            if ($hasCompletedOrder) {
                return __('common.exceptions.checkout.coupon.new_customers_only');
            }
        }

        if ($eligibility === 'specific_users' || $eligibility === 'specific_segment') {
            // No separate customer-segment table exists yet; specific_segment
            // is approximated by the same eligible_customer_ids membership
            // list as specific_users (both are "only these customers").
            $eligibleIds = $coupon->eligible_customer_ids ?? [];
            if (! in_array($customer->id, $eligibleIds, true)) {
                return __('common.exceptions.checkout.coupon.not_eligible');
            }
        }

        if ($coupon->usage_limit_total !== null && $coupon->times_used >= $coupon->usage_limit_total) {
            return __('common.exceptions.checkout.coupon.usage_limit_reached');
        }

        if ($coupon->usage_limit_per_customer !== null) {
            $used = CouponUsage::where('coupon_id', $coupon->id)
                ->where('customer_id', $customer->id)
                ->whereIn('status', [CouponUsage::STATUS_RESERVED, CouponUsage::STATUS_CONSUMED])
                ->count();
            if ($used >= $coupon->usage_limit_per_customer) {
                return __('common.exceptions.checkout.coupon.per_customer_limit_reached');
            }
        }

        if ($coupon->max_orders_per_customer_per_month !== null) {
            $usedThisMonth = CouponUsage::where('coupon_id', $coupon->id)
                ->where('customer_id', $customer->id)
                ->whereIn('status', [CouponUsage::STATUS_RESERVED, CouponUsage::STATUS_CONSUMED])
                ->where('used_at', '>=', $now->copy()->startOfMonth())
                ->count();
            if ($usedThisMonth >= $coupon->max_orders_per_customer_per_month) {
                return __('common.exceptions.checkout.coupon.monthly_limit_reached');
            }
        }

        if ($coupon->shipping_type_restriction !== null
            && $coupon->shipping_type_restriction !== CouponShippingTypeRestriction::All) {
            $lines = $this->normalizeAll($items);
            $mismatched = collect($lines)->contains(fn (array $l) => $l['shipping_type'] !== $coupon->shipping_type_restriction->value);

            if ($mismatched) {
                return __('common.exceptions.checkout.coupon.shipping_type_restricted', [
                    'type' => strtoupper($coupon->shipping_type_restriction->value),
                ]);
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
                'fulfillment_model' => ($item['is_admin'] ?? false) ? 'fbn' : (string) ($listing?->fulfillment_model ?? 'fbm'),
                'commission_base_unit_price' => (int) $item['unit_price'],
                'is_marketer' => false,
                'marketer_owner' => null,
                'marketer_commission_type' => null,
                'marketer_commission_raw' => 0,
            ];
        }

        // App\Models\CartItem (or any object exposing the same shape).
        //
        // Resolved through CartLineSource rather than `$item->vendorListing`
        // directly, so vendor/admin/campaign-marketer/independent-marketer
        // lines are all normalized correctly (enhancement.md P-02 — P-01
        // left this engine assuming vendor-listing-only cart items).
        $source = $item instanceof CartItem ? CartLineSource::resolve($item) : null;
        $listing = $source?->fulfilmentListing;
        $variant = $listing?->productVariant;
        $product = $variant?->product;

        // Marketer-commission owner (D5) and campaign fields, resolved once
        // here so computeMoneySplit() never has to re-touch CartLineSource.
        $isMarketer = $source?->marketerListingIdForOrderItem !== null;
        $marketerOwner = null;
        $marketerCommissionType = null;
        $marketerCommissionRaw = 0;
        if ($isMarketer && $item instanceof CartItem) {
            $campaign = $item->marketerListing?->invitation?->campaign;
            if ($campaign) {
                $marketerOwner = $campaign->vendor_listing_id ? 'vendor' : 'platform';
                $marketerCommissionType = (string) $campaign->commission_type;
                $marketerCommissionRaw = (int) ($campaign->marketer_commission_amount ?? 0);
            }
        }

        return [
            'key' => (string) ($item->id ?? $index),
            'vendor_id' => ($source && ! $source->isAdminSeller()) ? $source->sellerParty : null,
            'category_id' => $product?->category_id,
            'product_id' => $variant?->product_id,
            'unit_price' => (int) $item->unit_price,
            'quantity' => (int) $item->quantity,
            'line_subtotal' => (int) $item->unit_price * (int) $item->quantity,
            'shipping_type' => ($source === null || $source->isAdminSeller()) ? 'fbn' : match ($source->fulfillmentModel) {
                'fbn' => 'fbn',
                'cross_dock' => 'fbp',
                default => 'fbm',
            },
            'product' => $product,
            'listing_id' => $source?->sellable->id ?? $listing?->id,
            // D1/bug-fix: commission for marketer-sold lines is taken on the
            // vendor's own listing price (fulfilment listing), never the
            // marketer's resale price.
            'fulfillment_model' => $source?->isAdminSeller() ? 'fbn' : (string) ($source?->fulfillmentModel ?? 'fbm'),
            'commission_base_unit_price' => (int) ($listing?->price ?? $item->unit_price),
            'is_marketer' => $isMarketer,
            'marketer_owner' => $marketerOwner,
            'marketer_commission_type' => $marketerCommissionType,
            'marketer_commission_raw' => $marketerCommissionRaw,
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
