<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassifiedCategory extends Model
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
        'name_en', 'name_ar', 'slug', 'icon', 'parent_id',
        'requires_location_map', 'requires_sketch_upload',
        'contract_template_id', 'required_attachment_types',
        'is_active', 'sort_order',
    ];

    protected $casts = [
        'required_attachment_types' => 'array',
        'requires_location_map'     => 'boolean',
        'requires_sketch_upload'    => 'boolean',
        'is_active'                 => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function contractTemplate(): BelongsTo
    {
        return $this->belongsTo(ClassifiedContractTemplate::class, 'contract_template_id');
    }

    public function listings(): HasMany
    {
        return $this->hasMany(ClassifiedListing::class, 'classified_category_id');
    }

    public function getNameAttribute(): string
    {
        return app()->getLocale() === 'ar' ? $this->name_ar : $this->name_en;
    }
}
