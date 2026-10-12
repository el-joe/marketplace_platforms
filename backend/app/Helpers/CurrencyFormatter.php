<?php

namespace App\Helpers;

class CurrencyFormatter
{
    public static function formatPrice(int|float|string|null $amount, string $currency, ?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        $formatted = \App\Support\Money::format($amount);

        return $locale === 'ar'
            ? $formatted . ' ' . $currency
            : $currency . ' ' . $formatted;
    }
}
