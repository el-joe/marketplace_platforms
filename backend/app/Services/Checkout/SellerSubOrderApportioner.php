<?php

namespace App\Services\Checkout;

/**
 * Splits one seller's checkout money across the sub-orders that seller ships in.
 *
 * CheckoutPricingEngine prices a cart (priceCart) and splits its money (computeMoneySplit) per
 * seller party — a vendor, the platform, or a marketer. Place-order groups lines per seller AND
 * shipping method, so one seller can produce several sub-orders. Every seller-level figure must be
 * divided across those sub-orders, never copied onto each one, so that for each seller
 * Σ sub_orders.{tax, shipping, vendor_payout, …} equals the seller's figure, and therefore the order's.
 *
 * Figures the engine also produces per line (tax, warranty, commission, coupon funding, marketer
 * commission) are split exactly by line. Figures that only exist per seller (shipping, subsidy,
 * gateway fee) are split pro rata to each sub-order's gross subtotal. A split uses largest-remainder
 * rounding, so the parts always add back up to the seller's figure.
 */
final class SellerSubOrderApportioner
{
    /** money_split fields split exactly from the engine's per-line numbers: field => line field. */
    private const LINE_SPLIT_FIELDS = [
        'vendor_coupon_cost' => 'vendor_coupon_cost',
        'platform_coupon_cost' => 'platform_coupon_cost',
        'platform_commission' => 'raw_commission',
        'platform_commission_after_discount' => 'platform_commission_after_discount',
        'marketer_commission' => 'marketer_commission',
    ];

    /** money_split fields that only exist per seller, split pro rata to gross subtotal. */
    private const GROSS_SPLIT_FIELDS = ['gateway_fee', 'carrier_shipping_cost', 'shipping_gap'];

    /** Shipping-breakdown fields that only exist per seller, split pro rata to gross subtotal. */
    private const SHIPPING_SPLIT_FIELDS = ['shipping', 'platform_subsidy', 'vendor_contribution'];

    public function __construct(private readonly CheckoutPricingEngine $engine) {}

    /**
     * @param  array<string, list<string>>  $lineIdsByGroup  sub-order group key => cart line ids, all belonging to ONE seller
     * @param  array<string, PricedLine>  $pricedLines  cart line id => the engine's priced line
     * @param  PricedSubOrder|null  $pricedSubOrder  the seller's priced sub-order (priceCart)
     * @param  array<string, mixed>|null  $moneySplit  the seller's computeMoneySplit() sub_orders entry
     * @param  array<string, mixed>  $shipping  the seller's resolveVendorShipping() per_vendor entry
     * @return array<string, array{tax: int|float, warranty_revenue: int|float, shipping: int|float, platform_subsidy: int|float, vendor_contribution: int|float, billable_weight_grams: int|null, money_split: array<string, mixed>|null}>
     */
    public function apportion(
        array $lineIdsByGroup,
        array $pricedLines,
        ?PricedSubOrder $pricedSubOrder,
        ?array $moneySplit,
        array $shipping,
    ): array {
        if (count($lineIdsByGroup) === 1) {
            return [array_key_first($lineIdsByGroup) => [
                'tax' => $pricedSubOrder?->tax ?? 0,
                'warranty_revenue' => $pricedSubOrder?->warrantyTotal ?? 0,
                'shipping' => $shipping['shipping'] ?? 0,
                'platform_subsidy' => $shipping['platform_subsidy'] ?? 0,
                'vendor_contribution' => $shipping['vendor_contribution'] ?? 0,
                'billable_weight_grams' => $shipping['billable_weight_grams'] ?? null,
                'money_split' => $moneySplit,
            ]];
        }

        $sumLines = fn (callable $value): array => array_map(
            fn (array $lineIds) => array_sum(array_map(
                fn (string $id) => isset($pricedLines[$id]) ? $value($pricedLines[$id], $id) : 0,
                $lineIds,
            )),
            $lineIdsByGroup,
        );

        $gross = $sumLines(fn (PricedLine $line) => $line->lineSubtotal);
        $taxWeights = $sumLines(fn (PricedLine $line) => $line->lineTax + $line->warrantyTax);
        $warrantyWeights = $sumLines(fn (PricedLine $line) => $line->warrantyPrice);

        $tax = $this->split($pricedSubOrder?->tax ?? 0, $taxWeights, $gross);
        $warranty = $this->split($pricedSubOrder?->warrantyTotal ?? 0, $warrantyWeights, $gross);

        $shippingParts = [];
        foreach (self::SHIPPING_SPLIT_FIELDS as $field) {
            $shippingParts[$field] = $this->split($shipping[$field] ?? 0, $gross, $gross);
        }
        $billableWeight = isset($shipping['billable_weight_grams'])
            ? $this->splitWhole((int) $shipping['billable_weight_grams'], $gross)
            : null;

        $moneySplitParts = $moneySplit === null
            ? array_fill_keys(array_keys($lineIdsByGroup), null)
            : $this->splitMoney($moneySplit, $lineIdsByGroup, $gross, $shippingParts['vendor_contribution']);

        $result = [];
        foreach (array_keys($lineIdsByGroup) as $groupKey) {
            $result[$groupKey] = [
                'tax' => $tax[$groupKey],
                'warranty_revenue' => $warranty[$groupKey],
                'shipping' => $shippingParts['shipping'][$groupKey],
                'platform_subsidy' => $shippingParts['platform_subsidy'][$groupKey],
                'vendor_contribution' => $shippingParts['vendor_contribution'][$groupKey],
                'billable_weight_grams' => $billableWeight[$groupKey] ?? null,
                'money_split' => $moneySplitParts[$groupKey],
            ];
        }

        return $result;
    }

    /**
     * Splits the seller's money split per sub-order. Vendor payout is re-derived per sub-order with
     * the engine's formula from the split components, then any rounding residual is settled on the
     * largest sub-order so Σ payout equals the engine's seller payout exactly.
     *
     * @param  array<string, mixed>  $moneySplit
     * @param  array<string, list<string>>  $lineIdsByGroup
     * @param  array<string, int|float>  $gross
     * @param  array<string, int|float>  $vendorContribution
     * @return array<string, array<string, mixed>>
     */
    private function splitMoney(array $moneySplit, array $lineIdsByGroup, array $gross, array $vendorContribution): array
    {
        $lines = $moneySplit['lines'] ?? [];

        $parts = [];
        foreach (self::LINE_SPLIT_FIELDS as $field => $lineField) {
            $weights = array_map(
                fn (array $lineIds) => array_sum(array_map(fn (string $id) => $lines[$id][$lineField] ?? 0, $lineIds)),
                $lineIdsByGroup,
            );
            $parts[$field] = $this->split($moneySplit[$field] ?? 0, $weights, $gross);
        }

        $parts['commission_vat'] = $this->split(
            $moneySplit['commission_vat'] ?? 0,
            $parts['platform_commission_after_discount'],
            $gross,
        );

        foreach (self::GROSS_SPLIT_FIELDS as $field) {
            $parts[$field] = $this->split($moneySplit[$field] ?? 0, $gross, $gross);
        }

        $isVendorSeller = ($moneySplit['vendor_id'] ?? null) !== null;
        $marketerChargedToSeller = in_array($moneySplit['marketer_commission_owner'] ?? null, ['vendor', 'marketer'], true);

        $payout = [];
        foreach (array_keys($lineIdsByGroup) as $groupKey) {
            $payout[$groupKey] = $isVendorSeller
                ? $this->money($gross[$groupKey]
                    - $parts['vendor_coupon_cost'][$groupKey]
                    - $parts['platform_commission_after_discount'][$groupKey]
                    - $parts['commission_vat'][$groupKey]
                    - $parts['gateway_fee'][$groupKey]
                    - $vendorContribution[$groupKey]
                    - ($marketerChargedToSeller ? $parts['marketer_commission'][$groupKey] : 0))
                : 0;
        }

        $residual = $this->money(($moneySplit['vendor_payout'] ?? 0) - array_sum($payout));
        if ($residual != 0) {
            $largestGroup = array_keys($gross, max($gross), true)[0];
            $payout[$largestGroup] = $this->money($payout[$largestGroup] + $residual);
        }

        $result = [];
        foreach ($lineIdsByGroup as $groupKey => $lineIds) {
            $groupSplit = $moneySplit;
            foreach ($parts as $field => $values) {
                $groupSplit[$field] = $values[$groupKey];
            }
            $groupSplit['vendor_payout'] = $payout[$groupKey];
            $groupSplit['lines'] = array_intersect_key($lines, array_flip($lineIds));
            $result[$groupKey] = $groupSplit;
        }

        return $result;
    }

    /**
     * Largest-remainder split of $total by $weights (falling back to $fallbackWeights, then to equal
     * parts, when the weights are all zero). Handles a negative total by splitting its magnitude.
     *
     * @param  array<string, int|float>  $weights
     * @param  array<string, int|float>  $fallbackWeights
     * @return array<string, int|float>
     */
    private function split(int|float $total, array $weights, array $fallbackWeights): array
    {
        $zeros = array_fill_keys(array_keys($weights), 0);

        if ($total == 0) {
            return $zeros;
        }

        $positive = fn (array $w) => array_filter($w, fn ($value) => $value > 0);
        $effective = $positive($weights) ?: $positive($fallbackWeights) ?: array_fill_keys(array_keys($weights), 1);

        $allocated = $this->engine->allocateProRataDecimal(abs($total), $effective);
        $sign = $total < 0 ? -1 : 1;

        return array_map(
            fn ($value) => $this->money($sign * $value),
            array_replace($zeros, array_intersect_key($allocated, $zeros)),
        );
    }

    /**
     * @param  array<string, int|float>  $weights
     * @return array<string, int>
     */
    private function splitWhole(int $total, array $weights): array
    {
        $zeros = array_fill_keys(array_keys($weights), 0);
        $effective = array_filter($weights, fn ($value) => $value > 0) ?: array_fill_keys(array_keys($weights), 1);

        return array_map('intval', array_replace($zeros, array_intersect_key($this->engine->allocateProRata($total, $effective), $zeros)));
    }

    private function money(int|float $value): int|float
    {
        $rounded = round((float) $value, 4);

        return floor($rounded) == $rounded ? (int) $rounded : $rounded;
    }
}
