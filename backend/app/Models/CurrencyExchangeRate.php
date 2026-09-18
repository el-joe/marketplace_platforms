<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only: rows are inserted, never updated in place. Most recent
 * effective_at for a currency pair wins. No `updated_at` column exists on
 * this table by design — see the Phase 1 migration.
 */
class CurrencyExchangeRate extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    const UPDATED_AT = null;

    protected $fillable = [
        'from_currency_code',
        'to_currency_code',
        'rate_numerator',
        'rate_denominator',
        'effective_at',
    ];

    protected $casts = [
        'rate_numerator' => 'integer',
        'rate_denominator' => 'integer',
        'effective_at' => 'datetime',
    ];
}
