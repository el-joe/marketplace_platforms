<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InternationalShippingRate extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'origin_country_id',
        'destination_country_id',
        'carrier_id',
        'base_fee',
        'rate_per_kg',
        'customs_fee_flat',
        'min_eta_days',
        'max_eta_days',
        'is_active',
    ];

    protected $casts = [
        'base_fee' => 'integer',
        'rate_per_kg' => 'integer',
        'customs_fee_flat' => 'integer',
        'min_eta_days' => 'integer',
        'max_eta_days' => 'integer',
        'is_active' => 'boolean',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function originCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'origin_country_id');
    }

    public function destinationCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'destination_country_id');
    }

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(ShippingCarrier::class, 'carrier_id');
    }
}
