<?php

namespace App\Services\Shipping;

use App\Exceptions\CurrencyExchangeRateNotFoundException;
use App\Models\CurrencyExchangeRate;
use Illuminate\Support\Carbon;

/**
 * docs/plans/international_product_shipping.md Phase 2.
 *
 * Converts an integer minor-unit amount from one currency to another using
 * the most recent applicable currency_exchange_rates row. Same-currency
 * conversions short-circuit with no DB query. All math is integer-only
 * (intdiv), never float.
 */
class CurrencyConversionService
{
    /**
     * @return array{
     *     amount: int,
     *     rate_numerator: int,
     *     rate_denominator: int,
     *     effective_at: ?Carbon,
     * }
     */
    public function convert(int $amountMinor, string $fromCurrency, string $toCurrency): array
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

        $convertedAmount = intdiv($amountMinor * $rate->rate_numerator, $rate->rate_denominator);

        return [
            'amount' => $convertedAmount,
            'rate_numerator' => $rate->rate_numerator,
            'rate_denominator' => $rate->rate_denominator,
            'effective_at' => $rate->effective_at,
        ];
    }
}
