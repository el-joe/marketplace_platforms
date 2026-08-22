<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TravelInclusion extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'name_ar',
        'name_en',
        'slug',
        'icon',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(TravelPackage::class, 'travel_package_inclusions');
    }

    public function getNameAttribute(): string
    {
        return app()->getLocale() === 'ar' ? $this->name_ar : $this->name_en;
    }
}
