<?php

namespace App\Services\Checkout;

/**
 * Immutable output of CheckoutPricingEngine::priceCart(). Holds per-line,
 * per-sub-order-group and order-total figures, all reconciled bottom-up
 * (order totals are sums of sub-order totals, which are sums of line
 * totals), so `prepare` and `place-order` can never disagree as long as
 * they are fed the same inputs (D1/D2 in enhancement.md P-01).
 *
 * All monetary fields are BIGINT base-currency units.
 */
final class PricedCart
{
    /**
     * @param  PricedLine[]  $lines
     * @param  array<string, PricedSubOrder>  $subOrders  keyed by vendor_id (or 'platform')
     */
    public function __construct(
        public readonly array $lines,
        public readonly array $subOrders,
        public readonly int $subtotal,
        public readonly int $discount,
        public readonly int $loyaltyDiscount,
        public readonly int $shipping,
        public readonly int $codFee,
        public readonly int $tax,
        public readonly int $warrantyTotal,
        public readonly int $giftCardApplied,
        public readonly int $total,
        public readonly string $currency,
        // docs/plans/international_product_shipping.md Phase 3 / design
        // decision #5 (adopted Q2 answer): DDP — customs duty for
        // international lines is itemized as its own named charge, folded
        // into $total, never hidden inside $shipping. Mirrors how
        // $loyaltyDiscount was added to this class. Zero for an order with
        // no international lines.
        public readonly int $customsDuty = 0,
    ) {}

    /**
     * A comparable "signature" of every customer-facing figure. Used to
     * detect price drift between `prepare` and `place-order` (P-01 task 5).
     */
    public function signature(): array
    {
        return [
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'loyalty_discount' => $this->loyaltyDiscount,
            'shipping' => $this->shipping,
            'cod_fee' => $this->codFee,
            'tax' => $this->tax,
            'warranty_total' => $this->warrantyTotal,
            'gift_card_applied' => $this->giftCardApplied,
            'customs_duty' => $this->customsDuty,
            'total' => $this->total,
        ];
    }

    public function toArray(): array
    {
        return [
            'lines' => array_map(fn (PricedLine $l) => $l->toArray(), $this->lines),
            'sub_orders' => array_map(fn (PricedSubOrder $s) => $s->toArray(), $this->subOrders),
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'loyalty_discount' => $this->loyaltyDiscount,
            'shipping' => $this->shipping,
            'cod_fee' => $this->codFee,
            'tax' => $this->tax,
            'warranty_total' => $this->warrantyTotal,
            'gift_card_applied' => $this->giftCardApplied,
            'customs_duty' => $this->customsDuty,
            'total' => $this->total,
            'currency' => $this->currency,
        ];
    }
}
