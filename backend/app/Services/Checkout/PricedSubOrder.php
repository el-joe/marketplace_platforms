<?php

namespace App\Services\Checkout;

/**
 * A priced sub-order group (one vendor, or the platform, for a single
 * checkout). Immutable value object produced by
 * CheckoutPricingEngine::priceCart().
 */
final class PricedSubOrder
{
    public function __construct(
        public readonly ?string $vendorId,
        public readonly int|float $subtotal,
        public readonly int|float $discount,
        public readonly int|float $loyaltyDiscount,
        public readonly int|float $tax,
        public readonly int|float $warrantyTotal,
        public readonly int|float $shipping,
    ) {}

    public function toArray(): array
    {
        return [
            'vendor_id' => $this->vendorId,
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'loyalty_discount' => $this->loyaltyDiscount,
            'tax' => $this->tax,
            'warranty_total' => $this->warrantyTotal,
            'shipping' => $this->shipping,
        ];
    }
}
