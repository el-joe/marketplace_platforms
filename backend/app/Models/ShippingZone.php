<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShippingZone extends Model
{
    use HasUuids, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'country_id',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }

    public function originRates(): HasMany
    {
        return $this->hasMany(ShippingRate::class, 'origin_zone_id');
    }

    public function destinationRates(): HasMany
    {
        return $this->hasMany(ShippingRate::class, 'destination_zone_id');
    }

    public function warehouseExceptionalZones(): HasMany
    {
        return $this->hasMany(WarehouseExceptionalZone::class, 'destination_zone_id');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', 1);
    }

    public function scopeForCountry(Builder $query, string $countryId): Builder
    {
        return $query->where('country_id', $countryId);
    }

    // ── Accessors ──────────────────────────────────────────────────────────────

    public function getCityCountAttribute(): int
    {
        return $this->cities()->count();
    }

    public function getActiveRateCountAttribute(): int
    {
        return $this->destinationRates()->where('is_active', 1)->count();
    }

    public function hasGapRates(): bool
    {
        return $this->destinationRates()->whereColumn('carrier_rate', '>', 'base_fee')->exists();
    }
}
