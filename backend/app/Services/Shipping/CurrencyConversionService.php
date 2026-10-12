<?php

namespace App\Services\Shipping;

use App\Exceptions\CurrencyExchangeRateNotFoundException;
use App\Models\CurrencyExchangeRate;
use App\Support\Money;
use Illuminate\Support\Carbon;

/**
 * docs/plans/international_product_shipping.md Phase 2.
 *
 * Converts a money amount (integer or decimal) from one currency to another using
 * the most recent applicable currency_exchange_rates row. Same-currency
 * conversions short-circuit with no DB query. The fx rate is an integer
 * numerator/denominator pair; the result is rounded half-up to 2 dp.
 */
class CurrencyConversionService
{
    /**
     * @return array{
     *     amount: int|float,
     *     rate_numerator: int,
     *     rate_denominator: int,
     *     effective_at: ?Carbon,
     * }
     */
    public function convert(int|float $amountMinor, string $fromCurrency, string $toCurrency): array
    {
        if ($fromCurrency === $toCurrency) {
            return [
                'amount' => $amountMinor,
                'rate_numerator' => 1,
                'rate_denominator' => 1,
                'effective_at' => null,
            ];
        }

        $rate = CurrencyExchangeRate::query()
            ->where('from_currency_code', $fromCurrency)
            ->where('to_currency_code', $toCurrency)
            ->where('effective_at', '<=', now())
            ->orderByDesc('effective_at')
            ->first();

        if (! $rate) {
            throw new CurrencyExchangeRateNotFoundException($fromCurrency, $toCurrency);
        }

        $convertedAmount = Money::number(round($amountMinor * $rate->rate_numerator / $rate->rate_denominator, 2));

        return [
            'amount' => $convertedAmount,
            'rate_numerator' => $rate->rate_numerator,
            'rate_denominator' => $rate->rate_denominator,
            'effective_at' => $rate->effective_at,
        ];
    }
}
