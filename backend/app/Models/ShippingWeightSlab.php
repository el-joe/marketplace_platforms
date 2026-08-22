<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingWeightSlab extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'shipping_method_id',
        'country_id',
        'min_weight_grams',
        'max_weight_grams',
        'extra_fee',
        'is_active',
    ];

    protected $casts = [
        'min_weight_grams' => 'integer',
        'max_weight_grams' => 'integer',
        'extra_fee' => 'integer',
        'is_active' => 'boolean',
    ];

    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
