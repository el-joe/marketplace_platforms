<?php

namespace App\Services\Payments;

use Illuminate\Support\Facades\DB;

/**
 * enhancement.md P-05 task 6: convert an order-currency amount into the
 * gateway's configured currency using the `currencies.exchange_rate_to_base`
 * rates already maintained by App\Jobs\UpdateExchangeRatesJob, rather than
 * sending the order's raw integer amount under a different currency's code
 * (which silently over/undercharges whenever the two differ).
 *
 * `exchange_rate_to_base` is "how many units of this currency equal one
 * unit of the shared base currency" — so converting X of currency A into
 * currency B is: (X / rateA) * rateB.
 */
class CurrencyConversionService
{
    /**
     * @return array{amount: int, rate: float}
     */
    public function convert(int $amountCents, string $fromCurrency, string $toCurrency): array
    {
        if ($fromCurrency === $toCurrency) {
            return ['amount' => $amountCents, 'rate' => 1.0];
        }

        $rates = DB::table('currencies')
            ->whereIn('code', [$fromCurrency, $toCurrency])
            ->pluck('exchange_rate_to_base', 'code');

        $fromRate = (float) ($rates[$fromCurrency] ?? 0);
        $toRate = (float) ($rates[$toCurrency] ?? 0);

        if ($fromRate <= 0 || $toRate <= 0) {
            // No usable rate on record — never guess at money conversion;
            // charge the original amount rather than a fabricated one.
            return ['amount' => $amountCents, 'rate' => 1.0];
        }

        $rate = $toRate / $fromRate;
        $converted = (int) round($amountCents * $rate);

        return ['amount' => $converted, 'rate' => $rate];
    }
}
