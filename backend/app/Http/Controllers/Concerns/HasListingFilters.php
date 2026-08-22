<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait HasListingFilters
{
    /**
     * Apply common date-range filter to any query.
     * Looks for date_from / date_to in the request.
     * Column defaults to 'created_at'.
     */
    protected function applyDateRange(Builder $query, string $column = 'created_at'): Builder
    {
        $from = request('date_from');
        $to   = request('date_to');

        if ($from) {
            $query->where($column, '>=', \Carbon\Carbon::parse($from)->startOfDay());
        }
        if ($to) {
            $query->where($column, '<=', \Carbon\Carbon::parse($to)->endOfDay());
        }
        return $query;
    }

    /**
     * Apply a simple WHERE column = value filter if the request has that key.
     */
    protected function applyExactFilter(Builder $query, string $requestKey, string $column = ''): Builder
    {
        $column = $column ?: $requestKey;
        $value  = request($requestKey);
        if ($value !== null && $value !== '') {
            $query->where($column, $value);
        }
        return $query;
    }

    /**
     * Apply a LIKE search across multiple columns.
     */
    protected function applySearch(Builder $query, array $columns): Builder
    {
        $search = request('search');
        if ($search) {
            $query->where(function ($q) use ($search, $columns) {
                foreach ($columns as $i => $col) {
                    $method = $i === 0 ? 'where' : 'orWhere';
                    if (str_contains($col, '.')) {
                        [$relation, $field] = explode('.', $col, 2);
                        $q->$method(function ($sub) use ($relation, $field, $search) {
                            $sub->whereHas($relation, fn ($r) =>
                                $r->where($field, 'like', "%{$search}%"));
                        });
                    } else {
                        $q->{$method}($col, 'like', "%{$search}%");
                    }
                }
            });
        }
        return $query;
    }

    /**
     * Check if any filter is currently active (for filter card badge).
     */
    protected function hasActiveFilters(array $filterKeys = []): bool
    {
        $defaults = ['search', 'date_from', 'date_to', 'status', 'country_id',
                     'vendor_id', 'type', 'payment_method', 'carrier_id',
                     'category_id', 'zone_id'];
        $keys = array_unique(array_merge($defaults, $filterKeys));
        foreach ($keys as $key) {
            if (request($key) !== null && request($key) !== '') {
                return true;
            }
        }
        return false;
    }
}
