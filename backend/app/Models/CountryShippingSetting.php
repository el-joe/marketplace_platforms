<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CountryShippingSetting extends Model
{
    use HasUuids;
    protected $table = 'country_shipping_settings';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'country_id',
        'shipping_method_id',
        'is_active',
        'free_shipping_threshold',
    ];

    /** @var int Base currency unit (BIGINT) for money fields renamed in this model */
    protected $casts = [
        'is_active' => 'boolean',
        'free_shipping_threshold' => 'integer',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }
}
