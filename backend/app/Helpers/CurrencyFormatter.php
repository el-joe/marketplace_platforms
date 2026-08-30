<?php

namespace App\Helpers;

class CurrencyFormatter
{
    public static function formatPrice(int $amount, string $currency, ?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        $formatted = number_format($amount, 0);

        return $locale === 'ar'
            ? $formatted . ' ' . $currency
            : $currency . ' ' . $formatted;
    }
}
