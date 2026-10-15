<?php

namespace Tests\Unit\Services\Marketer;

use App\Services\Marketer\CommissionFeeService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Pure arithmetic of the commission fee: fee = pct x gross + fixed, capped at the gross; net never below 0. */
class CommissionFeeSplitTest extends TestCase
{
    /** @return array<string, array{0: float, 1: float, 2: float, 3: float, 4: float}> gross, pct, fixed, fee, net */
    public static function cases(): array
    {
        return [
            'influencer example (plan 9)' => [200, 5, 1, 11, 189],
            'affiliate example' => [80, 10, 2, 10, 70],
            'broker example' => [65, 10, 2, 8.5, 56.5],
            'percentage only' => [100, 10, 0, 10, 90],
            'fixed only' => [100, 0, 3, 3, 97],
            'no fee' => [100, 0, 0, 0, 100],
            'fee above the commission is capped' => [1, 10, 2, 1, 0],
            'zero commission' => [0, 10, 2, 0, 0],
            'two decimals' => [10.5, 10, 0, 1.05, 9.45],
        ];
    }

    #[DataProvider('cases')]
    public function test_split(float $gross, float $pct, float $fixed, float $fee, float $net): void
    {
        $split = CommissionFeeService::split($gross, $pct, $fixed);

        $this->assertEqualsWithDelta($fee, $split['fee'], 0.0001);
        $this->assertEqualsWithDelta($net, $split['net'], 0.0001);
        $this->assertEqualsWithDelta(round($gross, 2), $split['gross'], 0.0001);
        $this->assertGreaterThanOrEqual(0.0, $split['net']);
    }

    public function test_a_negative_commission_is_treated_as_zero(): void
    {
        $this->assertSame(['gross' => 0.0, 'fee' => 0.0, 'net' => 0.0], CommissionFeeService::split(-5, 10, 1));
    }
}
