<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * docs/plans/international_product_shipping.md Phase 2: thrown when no
 * currency_exchange_rates row exists (with effective_at <= now()) for a
 * given currency pair.
 */
class CurrencyExchangeRateNotFoundException extends RuntimeException
{
    public function __construct(string $fromCurrency, string $toCurrency)
    {
        parent::__construct(
            "No currency exchange rate found for {$fromCurrency} -> {$toCurrency}."
        );
    }
}
