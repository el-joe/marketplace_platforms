<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TravelCategory extends Model
{
    use HasUuids;

    protected static function booted(): void
    {
        static::saved(fn () => static::flushNavCaches());
        static::deleted(fn () => static::flushNavCaches());
    }

    private static function flushNavCaches(): void
    {
        \App\Services\Customer\CategoryService::flushCache();
        \App\Services\Customer\UnifiedCategoryService::flushCache();
    }

    protected $fillable = [
        'parent_id', 'name_en', 'name_ar', 'slug', 'icon', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(TravelPackage::class, 'travel_package_categories');
    }

    public function getNameAttribute(): string
    {
        return app()->getLocale() === 'ar' ? $this->name_ar : $this->name_en;
    }
}
