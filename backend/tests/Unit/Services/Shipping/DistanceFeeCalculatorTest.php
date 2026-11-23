<?php

namespace Tests\Unit\Services\Shipping;

use App\Enums\DistanceDeliveryStatus;
use App\Services\Shipping\DistancePricing\DistanceFeeCalculator;
use App\Services\Shipping\DistancePricing\DistanceQuote;
use App\Services\Shipping\DistancePricing\DistanceRule;
use App\Services\Shipping\DistancePricing\DistanceTier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DistanceFeeCalculatorTest extends TestCase
{
    /** Free within 3 km, 3–6 km → 1.5, 6 km and above → 2.5, nothing beyond 10 km. */
    private function rule(?float $freeRadius = 3.0, ?float $maxRadius = 10.0, ?array $tiers = null): DistanceRule
    {
        return new DistanceRule($freeRadius, $maxRadius, $tiers ?? [
            new DistanceTier(3.0, 6.0, 1.5),
            new DistanceTier(6.0, null, 2.5),
        ]);
    }

    public function test_haversine_measures_straight_line_kilometres(): void
    {
        // One degree of latitude is ~111.19 km anywhere on the globe.
        $this->assertEqualsWithDelta(111.19, DistanceFeeCalculator::haversineKm(23.0, 58.0, 24.0, 58.0), 0.01);
        $this->assertSame(0.0, DistanceFeeCalculator::haversineKm(23.5, 58.4, 23.5, 58.4));
        $this->assertSame(4.2, DistanceFeeCalculator::distanceBetween([23.5880, 58.3829], [23.5880 + 4.2 / 111.195, 58.3829]));
        $this->assertNull(DistanceFeeCalculator::distanceBetween(null, [23.5, 58.4]));
    }

    /**
     * @return array<string, array{float, DistanceDeliveryStatus, int|float}>
     */
    public static function distances(): array
    {
        return [
            'inside free radius' => [1.0, DistanceDeliveryStatus::FreeWithinRadius, 0],
            'free radius is inclusive' => [3.0, DistanceDeliveryStatus::FreeWithinRadius, 0],
            'rounds to 0.1 km before comparing (3.04 → 3.0)' => [3.04, DistanceDeliveryStatus::FreeWithinRadius, 0],
            'just past free radius (3.06 → 3.1)' => [3.06, DistanceDeliveryStatus::Tier, 1.5],
            'tier "to" is exclusive' => [6.0, DistanceDeliveryStatus::Tier, 2.5],
            'just under the tier end' => [5.9, DistanceDeliveryStatus::Tier, 1.5],
            'open-ended last tier' => [9.9, DistanceDeliveryStatus::Tier, 2.5],
            'max radius is inclusive' => [10.0, DistanceDeliveryStatus::Tier, 2.5],
            'beyond max radius' => [10.1, DistanceDeliveryStatus::Unavailable, 0],
        ];
    }

    #[DataProvider('distances')]
    public function test_quote_applies_radius_and_tier_boundaries(float $distanceKm, DistanceDeliveryStatus $status, int|float $fee): void
    {
        $quote = (new DistanceFeeCalculator)->quote($this->rule(), $distanceKm, false);

        $this->assertSame($status, $quote->status);
        $this->assertEquals($fee, $quote->fee);
    }

    public function test_order_amount_threshold_wins_over_tiers_but_not_over_max_radius(): void
    {
        $calculator = new DistanceFeeCalculator;

        $free = $calculator->quote($this->rule(), 8.0, true);
        $this->assertSame(DistanceDeliveryStatus::FreeByOrderAmount, $free->status);
        $this->assertEquals(0, $free->fee);

        $this->assertSame(DistanceDeliveryStatus::Unavailable, $calculator->quote($this->rule(), 12.0, true)->status);
    }

    public function test_missing_coordinates_fall_back_to_normal_pricing(): void
    {
        $quote = (new DistanceFeeCalculator)->quote($this->rule(), null, false);

        $this->assertSame(DistanceDeliveryStatus::Fallback, $quote->status);
        $this->assertSame(DistanceQuote::REASON_MISSING_COORDINATES, $quote->fallbackReason);
    }

    public function test_a_distance_no_tier_covers_falls_back_to_normal_pricing(): void
    {
        $rule = $this->rule(freeRadius: null, maxRadius: null, tiers: [new DistanceTier(0.0, 2.0, 1.0), new DistanceTier(4.0, 8.0, 2.0)]);
        $calculator = new DistanceFeeCalculator;

        $gap = $calculator->quote($rule, 3.0, false);
        $this->assertSame(DistanceDeliveryStatus::Fallback, $gap->status);
        $this->assertSame(DistanceQuote::REASON_NO_MATCHING_TIER, $gap->fallbackReason);

        $this->assertSame(DistanceDeliveryStatus::Fallback, $calculator->quote($rule, 8.0, false)->status);
        $this->assertSame(DistanceDeliveryStatus::Tier, $calculator->quote($rule, 0.0, false)->status);
    }

    public function test_tier_analysis_reports_overlaps_and_misplaced_open_tiers_as_errors(): void
    {
        $overlap = DistanceFeeCalculator::analyseTiers([
            ['from_km' => 0, 'to_km' => 5],
            ['from_km' => 4, 'to_km' => null],
        ]);
        $this->assertSame(['tier_overlap'], array_column($overlap['errors'], 'key'));

        $openNotLast = DistanceFeeCalculator::analyseTiers([
            ['from_km' => 0, 'to_km' => null],
            ['from_km' => 5, 'to_km' => 8],
        ]);
        $this->assertContains('tier_open_ended_not_last', array_column($openNotLast['errors'], 'key'));

        $inverted = DistanceFeeCalculator::analyseTiers([['from_km' => 5, 'to_km' => 5]]);
        $this->assertSame(['tier_inverted'], array_column($inverted['errors'], 'key'));

        $radii = DistanceFeeCalculator::analyseTiers([], 10.0, 10.0);
        $this->assertSame(['free_radius_not_below_max'], array_column($radii['errors'], 'key'));
    }

    public function test_tier_analysis_warns_about_gaps_only(): void
    {
        $result = DistanceFeeCalculator::analyseTiers([
            ['from_km' => 3, 'to_km' => 5],
            ['from_km' => 6, 'to_km' => 8],
        ], freeRadiusKm: 2.0, maxServiceRadiusKm: 10.0);

        $this->assertSame([], $result['errors']);
        $this->assertSame(
            [['from' => 2, 'to' => 3], ['from' => 5, 'to' => 6], ['from' => 8, 'to' => 10]],
            array_column($result['warnings'], 'params'),
        );

        $contiguous = DistanceFeeCalculator::analyseTiers([
            ['from_km' => 3, 'to_km' => 6],
            ['from_km' => 6, 'to_km' => null],
        ], freeRadiusKm: 3.0, maxServiceRadiusKm: 10.0);
        $this->assertSame(['errors' => [], 'warnings' => []], $contiguous);
    }
}
