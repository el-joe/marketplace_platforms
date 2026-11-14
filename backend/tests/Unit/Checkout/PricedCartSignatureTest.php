<?php

namespace Tests\Unit\Checkout;

use App\Services\Checkout\PricedCart;
use PHPUnit\Framework\TestCase;

/**
 * `prepare` and `place-order` compare PricedCart signatures strictly to detect price drift. The same
 * amount reached as an int (1) on one path and a float (1.0) on the other must not count as drift —
 * a multi-shipment COD cart whose per-group fees sum to a whole number used to be refused as
 * "prices changed" even though every figure was identical.
 */
class PricedCartSignatureTest extends TestCase
{
    private function cart(int|float $codFee, int|float $total): PricedCart
    {
        return new PricedCart(
            lines: [], subOrders: [], subtotal: 77.1, discount: 0, loyaltyDiscount: 0, shipping: 0,
            codFee: $codFee, tax: 3.85, warrantyTotal: 0, giftCardApplied: 0, total: $total, currency: 'OMR',
        );
    }

    public function test_the_same_amounts_held_as_int_or_float_give_identical_signatures(): void
    {
        $this->assertSame($this->cart(1, 82)->signature(), $this->cart(1.0, 82.0)->signature());
    }

    public function test_a_real_amount_difference_still_changes_the_signature(): void
    {
        $this->assertNotSame($this->cart(1, 81.95)->signature(), $this->cart(0.5, 81.45)->signature());
    }
}
