<?php

namespace App\Services\Checkout;

/**
 * One priced cart/order line. Immutable value object produced by
 * CheckoutPricingEngine::priceCart(). All monetary fields are BIGINT
 * base-currency units (never *100/100).
 */
final class PricedLine
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $vendorId,
        public readonly int $unitPrice,
        public readonly int $quantity,
        public readonly int $lineSubtotal,
        public readonly int $lineDiscount,
        public readonly int $lineLoyaltyDiscount,
        public readonly int $taxable,
        public readonly int $lineTax,
        public readonly int $warrantyPrice,
        public readonly int $warrantyTax,
        public readonly int $lineTotal,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'vendor_id' => $this->vendorId,
            'unit_price' => $this->unitPrice,
            'quantity' => $this->quantity,
            'line_subtotal' => $this->lineSubtotal,
            'line_discount' => $this->lineDiscount,
            'line_loyalty_discount' => $this->lineLoyaltyDiscount,
            'taxable' => $this->taxable,
            'line_tax' => $this->lineTax,
            'warranty_price' => $this->warrantyPrice,
            'warranty_tax' => $this->warrantyTax,
            'line_total' => $this->lineTotal,
        ];
    }
}
