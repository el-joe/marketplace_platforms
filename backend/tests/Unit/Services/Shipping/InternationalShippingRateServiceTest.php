<?php

namespace Tests\Unit\Services\Shipping;

use App\Exceptions\InternationalShippingRateNotFoundException;
use App\Models\Country;
use App\Models\InternationalShippingRate;
use App\Services\Shipping\InternationalShippingRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * docs/plans/international_product_shipping.md Phase 2.
 */
class InternationalShippingRateServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): InternationalShippingRateService
    {
        return app(InternationalShippingRateService::class);
    }

    private function makeRate(Country $origin, Country $destination, array $overrides = []): InternationalShippingRate
    {
        return InternationalShippingRate::create(array_merge([
            'id' => (string) Str::uuid(),
            'origin_country_id' => $origin->id,
            'destination_country_id' => $destination->id,
            'carrier_id' => null,
            'base_fee' => 1000,
            'rate_per_kg' => 500,
            'customs_fee_flat' => null,
            'min_eta_days' => 5,
            'max_eta_days' => 10,
            'is_active' => true,
        ], $overrides));
    }

    public function test_throws_when_no_rate_found_for_corridor(): void
    {
        $origin = Country::factory()->create();
        $destination = Country::factory()->create();

        $this->expectException(InternationalShippingRateNotFoundException::class);

        $this->service()->quote($origin->id, $destination->id, 500);
    }

    public function test_throws_when_only_inactive_rate_exists(): void
    {
        $origin = Country::factory()->create();
        $destination = Country::factory()->create();
        $this->makeRate($origin, $destination, ['is_active' => false]);

        $this->expectException(InternationalShippingRateNotFoundException::class);

        $this->service()->quote($origin->id, $destination->id, 500);
    }

    public function test_weight_rounds_up_to_next_full_kilogram(): void
    {
        $origin = Country::factory()->create();
        $destination = Country::factory()->create();
        $this->makeRate($origin, $destination, [
            'base_fee' => 1000,
            'rate_per_kg' => 500,
            'customs_fee_flat' => null,
        ]);

        // 1001g must round up to 2kg worth of per-kg fee, not 1kg.
        $result = $this->service()->quote($origin->id, $destination->id, 1001);

        $this->assertSame(1000 + (500 * 2), $result['shipping_fee']);
        $this->assertSame(0, $result['customs_fee']);
        $this->assertSame(1000 + (500 * 2), $result['total_fee']);
    }

    public function test_exact_kilogram_weight_does_not_round_up_an_extra_kilogram(): void
    {
        $origin = Country::factory()->create();
        $destination = Country::factory()->create();
        $this->makeRate($origin, $destination, [
            'base_fee' => 1000,
            'rate_per_kg' => 500,
        ]);

        $result = $this->service()->quote($origin->id, $destination->id, 2000);

        $this->assertSame(1000 + (500 * 2), $result['shipping_fee']);
    }

    public function test_customs_fee_flat_is_added_to_total_when_present(): void
    {
        $origin = Country::factory()->create();
        $destination = Country::factory()->create();
        $this->makeRate($origin, $destination, [
            'base_fee' => 1000,
            'rate_per_kg' => 500,
            'customs_fee_flat' => 250,
        ]);

        $result = $this->service()->quote($origin->id, $destination->id, 500);

        $this->assertSame(1500, $result['shipping_fee']);
        $this->assertSame(250, $result['customs_fee']);
        $this->assertSame(1750, $result['total_fee']);
    }

    public function test_eta_range_is_returned_from_the_matched_rate(): void
    {
        $origin = Country::factory()->create();
        $destination = Country::factory()->create();
        $this->makeRate($origin, $destination, ['min_eta_days' => 3, 'max_eta_days' => 7]);

        $result = $this->service()->quote($origin->id, $destination->id, 500);

        $this->assertSame(3, $result['min_eta_days']);
        $this->assertSame(7, $result['max_eta_days']);
    }
}
