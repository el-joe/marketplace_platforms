<?php

namespace Tests\Unit\Services\Shipping;

use App\Exceptions\CurrencyExchangeRateNotFoundException;
use App\Models\CurrencyExchangeRate;
use App\Services\Shipping\CurrencyConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * docs/plans/international_product_shipping.md Phase 2.
 */
class CurrencyConversionServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): CurrencyConversionService
    {
        return app(CurrencyConversionService::class);
    }

    private function makeRate(array $overrides = []): CurrencyExchangeRate
    {
        return CurrencyExchangeRate::create(array_merge([
            'id' => (string) Str::uuid(),
            'from_currency_code' => 'AED',
            'to_currency_code' => 'EGP',
            'rate_numerator' => 850,
            'rate_denominator' => 100,
            'effective_at' => now()->subDay(),
        ], $overrides));
    }

    public function test_throws_when_no_rate_found_for_currency_pair(): void
    {
        $this->expectException(CurrencyExchangeRateNotFoundException::class);

        $this->service()->convert(10000, 'AED', 'EGP');
    }

    public function test_same_currency_short_circuits_with_no_db_query(): void
    {
        DB::enableQueryLog();

        $result = $this->service()->convert(12345, 'AED', 'AED');

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame([], $queries, 'Expected no DB queries for a same-currency conversion.');
        $this->assertSame(12345, $result['amount']);
        $this->assertSame(1, $result['rate_numerator']);
        $this->assertSame(1, $result['rate_denominator']);
        $this->assertNull($result['effective_at']);
    }

    public function test_converts_using_integer_math_no_float(): void
    {
        $this->makeRate([
            'rate_numerator' => 850,
            'rate_denominator' => 100,
        ]);

        $result = $this->service()->convert(10000, 'AED', 'EGP');

        // intdiv(10000 * 850, 100) = intdiv(8_500_000, 100) = 85000
        $this->assertSame(85000, $result['amount']);
        $this->assertIsInt($result['amount']);
        $this->assertSame(850, $result['rate_numerator']);
        $this->assertSame(100, $result['rate_denominator']);
    }

    public function test_most_recent_effective_at_wins_when_multiple_rates_exist(): void
    {
        $this->makeRate([
            'rate_numerator' => 800,
            'rate_denominator' => 100,
            'effective_at' => now()->subDays(10),
        ]);
        $newest = $this->makeRate([
            'rate_numerator' => 900,
            'rate_denominator' => 100,
            'effective_at' => now()->subDay(),
        ]);
        $this->makeRate([
            'rate_numerator' => 700,
            'rate_denominator' => 100,
            'effective_at' => now()->subDays(5),
        ]);

        $result = $this->service()->convert(10000, 'AED', 'EGP');

        $this->assertSame(900, $result['rate_numerator']);
        $this->assertSame($newest->effective_at->toDateTimeString(), $result['effective_at']->toDateTimeString());
    }

    public function test_ignores_future_effective_at_rates(): void
    {
        $this->makeRate([
            'rate_numerator' => 800,
            'rate_denominator' => 100,
            'effective_at' => now()->subDay(),
        ]);
        $this->makeRate([
            'rate_numerator' => 999,
            'rate_denominator' => 100,
            'effective_at' => now()->addDay(),
        ]);

        $result = $this->service()->convert(10000, 'AED', 'EGP');

        $this->assertSame(800, $result['rate_numerator']);
    }
}
