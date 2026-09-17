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
        public readonly int $subtotal,
        public readonly int $discount,
        public readonly int $loyaltyDiscount,
        public readonly int $tax,
        public readonly int $warrantyTotal,
        public readonly int $shipping,
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
