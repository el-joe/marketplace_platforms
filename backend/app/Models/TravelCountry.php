<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TravelCountry extends Model
{
    use HasUuids;

    protected $fillable = [
        'iso_code_2',
        'iso_code_3',
        'name_en',
        'name_ar',
        'flag_emoji',
        'continent',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function cities(): HasMany
    {
        return $this->hasMany(TravelCity::class);
    }

    public function packages(): HasMany
    {
        return $this->hasMany(TravelPackage::class, 'destination_travel_country_id');
    }
}
