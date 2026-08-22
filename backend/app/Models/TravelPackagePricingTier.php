<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TravelPackagePricingTier extends Model
{
    use HasUuids;

    protected $fillable = [
        'travel_package_id',
        'travelers_count',
        'price',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'travelers_count' => 'integer',
            'price' => 'integer',
            'position' => 'integer',
        ];
    }

    public function travelPackage(): BelongsTo
    {
        return $this->belongsTo(TravelPackage::class);
    }
}
