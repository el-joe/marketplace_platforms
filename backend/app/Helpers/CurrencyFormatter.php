<?php

namespace App\Helpers;

class CurrencyFormatter
{
    /**
     * Format a price given in cents for display, respecting locale-specific
     * symbol placement (Arabic: number first, English: currency first).
     */
    public static function formatPrice(int $cents, string $currency, ?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        $amount = number_format($cents / 100, 2);

        return $locale === 'ar'
            ? $amount . ' ' . $currency
            : $currency . ' ' . $amount;
    }
}
