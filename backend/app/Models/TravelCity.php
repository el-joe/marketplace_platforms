<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TravelCity extends Model
{
    use HasUuids;

    protected $fillable = [
        'travel_country_id',
        'name_en',
        'name_ar',
        'latitude',
        'longitude',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'latitude'  => 'float',
            'longitude' => 'float',
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(TravelCountry::class, 'travel_country_id');
    }

    public function packages(): HasMany
    {
        return $this->hasMany(TravelPackage::class, 'destination_travel_city_id');
    }
}
