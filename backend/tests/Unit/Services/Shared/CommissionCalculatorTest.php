<?php

namespace Tests\Unit\Services\Shared;

use App\Services\Shared\CommissionCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CommissionCalculatorTest extends TestCase
{
    /** @return array<string, array{int, int}> price => [price, expected] with threshold 60, high 10%, standard 6%, min 5 */
    public static function tieredCases(): array
    {
        return [
            'cheap, floor wins' => [30, 5],
            'above threshold' => [100, 6],
            'well above threshold' => [200, 12],
        ];
    }

    #[DataProvider('tieredCases')]
    public function test_tiered_rate_with_minimum_floor(int $price, int $expected): void
    {
        $this->assertSame($expected, CommissionCalculator::calculate($price, 6.0, 60, 10.0, 5));
    }

    public function test_tiering_disabled_uses_standard_rate(): void
    {
        $this->assertSame(2, CommissionCalculator::calculate(30, 8.0));
    }

    public function test_price_exactly_at_threshold_uses_high_rate(): void
    {
        $this->assertSame(6, CommissionCalculator::calculate(60, 6.0, 60, 10.0));
    }

    public function test_price_just_above_threshold_uses_standard_rate_floored(): void
    {
        $this->assertSame(3, CommissionCalculator::calculate(61, 6.0, 60, 10.0));
    }

    public function test_flat_amount_is_per_unit(): void
    {
        $this->assertSame(15, CommissionCalculator::calculate(300, 0.0, 0, 0.0, 0, 3, 5, true));
    }

    public function test_flat_amount_ignored_unless_included(): void
    {
        $this->assertSame(0, CommissionCalculator::calculate(300, 0.0, 0, 0.0, 0, 3, 5, false));
    }

    public function test_minimum_floor_applies_when_percentage_is_lower(): void
    {
        $this->assertSame(5, CommissionCalculator::calculate(30, 10.0, 0, 0.0, 5));
    }

    public function test_tier_is_chosen_by_unit_price_not_line_total(): void
    {
        // 3 × 30 = 90 line total is above the threshold, but each unit is 30 <= 60.
        $this->assertSame(9, CommissionCalculator::calculate(90, 6.0, 60, 10.0, 0, 3));
    }

    public function test_minimum_floor_is_per_unit(): void
    {
        $this->assertSame(15, CommissionCalculator::calculate(90, 6.0, 0, 0.0, 5, 3));
    }
}
