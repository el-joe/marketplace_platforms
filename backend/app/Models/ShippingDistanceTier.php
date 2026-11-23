<?php

namespace App\Models;

use App\Casts\DecimalMoney;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One distance band of a ShippingDistancePricing: [from_km, to_km) → price; to_km NULL = open-ended.
 */
class ShippingDistanceTier extends Model
{
    use HasUuids;

    protected $fillable = [
        'shipping_distance_pricing_id',
        'from_km',
        'to_km',
        'price',
    ];

    protected function casts(): array
    {
        return [
            'from_km' => 'float',
            'to_km' => 'float',
            'price' => DecimalMoney::class,
        ];
    }

    public function pricing(): BelongsTo
    {
        return $this->belongsTo(ShippingDistancePricing::class, 'shipping_distance_pricing_id');
    }
}
