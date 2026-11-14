<?php

namespace Tests\Unit\Checkout;

use App\Services\Checkout\CheckoutPricingEngine;
use App\Services\Checkout\PricedLine;
use App\Services\Checkout\PricedSubOrder;
use App\Services\Checkout\SellerSubOrderApportioner;
use App\Services\WarrantyPlanService;
use PHPUnit\Framework\TestCase;

/**
 * One vendor whose lines ship by different methods is placed as several sub-orders. The engine prices
 * and splits money per vendor, so every vendor-level figure must be divided across those sub-orders —
 * NAWY-20261102-Z70HYW stored the vendor's whole tax (19,504.85) and payout (390,097) on each of its
 * three sub-orders, tripling both.
 */
class SellerSubOrderApportionerTest extends TestCase
{
    private function apportioner(): SellerSubOrderApportioner
    {
        return new SellerSubOrderApportioner(new CheckoutPricingEngine(new WarrantyPlanService));
    }

    private function line(string $id, int|float $subtotal, int|float $tax, int|float $warranty = 0, int|float $warrantyTax = 0): PricedLine
    {
        return new PricedLine(
            id: $id, vendorId: 'vendor-1', unitPrice: $subtotal, quantity: 1, lineSubtotal: $subtotal,
            lineDiscount: 0, lineLoyaltyDiscount: 0, taxable: $subtotal, lineTax: $tax,
            warrantyPrice: $warranty, warrantyTax: $warrantyTax, lineTotal: $subtotal + $tax,
        );
    }

    /** @return array<string, mixed> */
    private function moneySplit(): array
    {
        return [
            'vendor_id' => 'vendor-1',
            'vendor_coupon_cost' => 0,
            'platform_coupon_cost' => 0,
            'platform_commission' => 39009.7,
            'platform_commission_after_discount' => 39009.7,
            'commission_vat' => 1950.49,
            'commission_vat_rate' => 5.0,
            'marketer_commission' => 0,
            'marketer_commission_owner' => null,
            'gateway_fee' => 1170.29,
            'carrier_shipping_cost' => 30,
            'shipping_gap' => 0,
            'vendor_payout' => 347966.52,
            'lines' => [
                'a' => ['vendor_coupon_cost' => 0, 'platform_coupon_cost' => 0, 'raw_commission' => 37500, 'marketer_commission' => 0, 'platform_commission_after_discount' => 37500],
                'b' => ['vendor_coupon_cost' => 0, 'platform_coupon_cost' => 0, 'raw_commission' => 1500, 'marketer_commission' => 0, 'platform_commission_after_discount' => 1500],
                'c' => ['vendor_coupon_cost' => 0, 'platform_coupon_cost' => 0, 'raw_commission' => 9.7, 'marketer_commission' => 0, 'platform_commission_after_discount' => 9.7],
            ],
        ];
    }

    public function test_a_vendor_split_across_shipping_methods_is_divided_not_duplicated(): void
    {
        $lines = [
            'a' => $this->line('a', 375000, 18750),
            'b' => $this->line('b', 15000, 750, warranty: 100, warrantyTax: 5),
            'c' => $this->line('c', 97, 4.85),
        ];
        $groups = ['v|express|' => ['a'], 'v|standard|' => ['b'], 'v|economy|' => ['c']];
        $priced = new PricedSubOrder('vendor-1', 390097, 0, 0, 19509.85, 100, 30);
        $shipping = ['shipping' => 30, 'platform_subsidy' => 10, 'vendor_contribution' => 5, 'billable_weight_grams' => 1000];

        $result = $this->apportioner()->apportion($groups, $lines, $priced, $this->moneySplit(), $shipping);

        $this->assertSame(18750, $result['v|express|']['tax']);
        $this->assertSame(755, $result['v|standard|']['tax']);
        $this->assertSame(4.85, $result['v|economy|']['tax']);
        $this->assertSame([0, 100, 0], array_column($result, 'warranty_revenue'));
        $this->assertSame([37500, 1500, 9.7], array_column(array_column($result, 'money_split'), 'platform_commission_after_discount'));

        $sum = fn (string $field) => round(array_sum(array_column($result, $field)), 4);
        $sumSplit = fn (string $field) => round(array_sum(array_column(array_column($result, 'money_split'), $field)), 4);

        $this->assertEqualsWithDelta(19509.85, $sum('tax'), 0.00001);
        $this->assertEqualsWithDelta(30, $sum('shipping'), 0.00001);
        $this->assertEqualsWithDelta(10, $sum('platform_subsidy'), 0.00001);
        $this->assertEqualsWithDelta(5, $sum('vendor_contribution'), 0.00001);
        $this->assertSame(1000, array_sum(array_column($result, 'billable_weight_grams')));
        $this->assertEqualsWithDelta(1950.49, $sumSplit('commission_vat'), 0.00001);
        $this->assertEqualsWithDelta(1170.29, $sumSplit('gateway_fee'), 0.00001);
        $this->assertEqualsWithDelta(347966.52, $sumSplit('vendor_payout'), 0.00001);

        foreach ($result as $groupKey => $part) {
            $this->assertSame($groups[$groupKey], array_keys($part['money_split']['lines']), 'each sub-order keeps only its own lines');
            $this->assertLessThan(390097, $part['money_split']['vendor_payout'] + 0, 'no sub-order carries the whole vendor payout');
        }
    }

    public function test_a_vendor_in_a_single_sub_order_keeps_the_engine_figures_unchanged(): void
    {
        $split = $this->moneySplit();
        $priced = new PricedSubOrder('vendor-1', 390097, 0, 0, 19509.85, 100, 30);
        $shipping = ['shipping' => 30, 'platform_subsidy' => 10, 'vendor_contribution' => 5, 'billable_weight_grams' => 1000];

        $result = $this->apportioner()->apportion(['v||' => ['a', 'b', 'c']], [], $priced, $split, $shipping);

        $this->assertSame([
            'v||' => [
                'tax' => 19509.85,
                'warranty_revenue' => 100,
                'shipping' => 30,
                'platform_subsidy' => 10,
                'vendor_contribution' => 5,
                'billable_weight_grams' => 1000,
                'money_split' => $split,
            ],
        ], $result);
    }

    public function test_the_platform_seller_never_receives_a_vendor_payout(): void
    {
        $split = array_merge($this->moneySplit(), ['vendor_id' => null, 'vendor_payout' => 0]);
        $lines = ['a' => $this->line('a', 100, 5), 'b' => $this->line('b', 50, 2.5)];

        $result = $this->apportioner()->apportion(
            ['p|x|' => ['a'], 'p|y|' => ['b']],
            $lines,
            new PricedSubOrder(null, 150, 0, 0, 7.5, 0, 0),
            $split,
            [],
        );

        $this->assertSame([0, 0], array_column(array_column($result, 'money_split'), 'vendor_payout'));
        $this->assertSame([5, 2.5], array_column($result, 'tax'));
    }
}
