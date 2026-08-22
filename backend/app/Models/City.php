<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class City extends Model
{
    use HasUuids, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'country_id',
        'name_ar',
        'name_en',
        'latitude',
        'longitude',
        'shipping_zone_id',
        'is_active',
        'cod_available',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'cod_available' => 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function shippingZone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->whereNull('shipping_zone_id');
    }

    public function scopeForCountry(Builder $query, string $countryId): Builder
    {
        return $query->where('country_id', $countryId);
    }

    // ── Accessors ──────────────────────────────────────────────────────────────

    public function getNameAttribute(): string
    {
        return app()->getLocale() === 'ar' ? $this->name_ar : $this->name_en;
    }
}
