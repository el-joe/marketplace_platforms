<?php

namespace App\Traits;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

trait HasCurrencyAwareAggregates
{
    /**
     * Group a money column by currency instead of summing across currencies.
     *
     * Returns a Collection of objects with {currency, total} where total is
     * the raw integer/decimal from the DB (cents if the column is stored in cents).
     */
    public function sumByCurrency(Builder $query, string $column, string $currencyColumn = 'currency'): Collection
    {
        return $query->selectRaw("{$currencyColumn} as currency, SUM({$column}) as total")
            ->groupBy($currencyColumn)
            ->get();
    }

    /**
     * Convert a multi-currency Collection (from sumByCurrency) to a single
     * USD-equivalent float for display purposes only.
     *
     * exchange_rate_to_base stores "1 USD = X local", so local→USD = amount / rate.
     * e.g. AED rate 3.673 → 1 USD ≈ 3.67 AED ✓
     *
     * Never use this as a silent default when per-currency breakdown is meaningful.
     */
    public function consolidatedUsd(Collection $byCurrency, string $totalKey = 'total'): float
    {
        $rates = Cache::remember('currency_rates_to_base', 300, fn () =>
            Currency::pluck('exchange_rate_to_base', 'code')->all()
        );

        return $byCurrency->sum(fn ($row) =>
            ($row->{$totalKey} / 100) / ($rates[$row->currency] ?? 1)
        );
    }

    /**
     * Group a money column by country, joining the countries table so callers
     * get country_id, name_en, currency_code, and total in one query.
     *
     * Use when the source table has a country_id FK but no currency column.
     */
    public function sumByCountry(Builder $query, string $column, string $countryIdColumn = 'country_id'): Collection
    {
        return $query
            ->join('countries', 'countries.id', '=', $countryIdColumn)
            ->selectRaw("countries.id as country_id, countries.name_en, countries.currency_code, SUM({$column}) as total")
            ->groupBy('countries.id', 'countries.name_en', 'countries.currency_code')
            ->get();
    }
}
