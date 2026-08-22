<?php

namespace App\Models;

use App\Enums\AddressType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Address extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'addressable_type',
        'addressable_id',
        'label',
        'recipient_name',
        'recipient_phone',
        'country_id',
        'city_id',
        'area',
        'street_address',
        'building',
        'floor',
        'apartment',
        'postal_code',
        'landmark',
        'latitude',
        'longitude',
        'is_default',
        'address_type',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
            'address_type' => AddressType::class,
        ];
    }

    public function addressable(): MorphTo
    {
        return $this->morphTo();
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
