<?php

namespace App\Support;

/**
 * Display + rounding helpers for DECIMAL(19,4) money, so a 2.00 fee reads "2" and a 0.10 VAT reads "0.10".
 */
final class Money
{
    /** Whole amounts without decimals ("5,900"), fractional ones with two ("5,900.50"). */
    public static function format(int|float|string|null $amount, int $decimals = 2): string
    {
        $value = round((float) $amount, $decimals);
        $isWhole = abs($value - round($value)) < 0.000001;

        return number_format($value, $isWhole ? 0 : $decimals);
    }

    /** Whole amounts as int, fractional as float: keeps JSON like 1000 (not 1000.0) for existing API clients. */
    public static function number(int|float|string|null $amount): int|float
    {
        $value = round((float) $amount, 4);

        return abs($value - round($value)) < 0.00005 ? (int) round($value) : $value;
    }

    /** Round half up to 2 decimals (the precision money is charged and displayed at). */
    public static function round(int|float|string|null $amount): float
    {
        return round((float) $amount, 2);
    }
}
