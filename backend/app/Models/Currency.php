<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Currency extends Model
{
    protected $primaryKey = 'code';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'code',
        'name',
        'symbol',
        'decimal_places',
        'base_currency_code',
        'exchange_rate_to_base',
        'is_active',
        'is_manually_overridden',
        'rate_updated_at',
    ];

    protected $casts = [
        'decimal_places' => 'integer',
        'exchange_rate_to_base' => 'decimal:6',
        'is_active' => 'boolean',
        'is_manually_overridden' => 'boolean',
        'rate_updated_at' => 'datetime',
    ];

    public function countries(): HasMany
    {
        return $this->hasMany(Country::class, 'currency_code', 'code');
    }
}
