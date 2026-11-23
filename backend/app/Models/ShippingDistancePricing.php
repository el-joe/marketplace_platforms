<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Distance-based delivery rule for one (destination zone, shipping method) pair (client item #19).
 *
 * @property float|null $free_radius_km
 * @property float|null $max_service_radius_km
 */
class ShippingDistancePricing extends Model
{
    use HasUuids;

    protected $fillable = [
        'shipping_zone_id',
        'shipping_method_id',
        'free_radius_km',
        'max_service_radius_km',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'free_radius_km' => 'float',
            'max_service_radius_km' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }

    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    public function tiers(): HasMany
    {
        return $this->hasMany(ShippingDistanceTier::class)->orderBy('from_km');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'shipping_distance_pricing_categories');
    }
}
