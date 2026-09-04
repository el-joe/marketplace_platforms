<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomPage extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'name_en',
        'name_ar',
        'description_en',
        'description_ar',
        'has_filters',
        'is_active',
        'sort_order',
        'seo_title_en',
        'seo_title_ar',
        'seo_description_en',
        'seo_description_ar',
    ];

    protected $casts = [
        'has_filters' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => \App\Services\Customer\CategoryService::flushCache());
        static::deleted(fn () => \App\Services\Customer\CategoryService::flushCache());
    }

    public function getNameAttribute(): string
    {
        $locale = app()->getLocale();
        return $this->{'name_' . $locale} ?? $this->name_en ?? '';
    }

    public function getImageUrlAttribute(): ?string
    {
        $file = $this->relationLoaded('primaryImage')
            ? $this->primaryImage
            : $this->primaryImage()->first();

        $file ??= $this->relationLoaded('files')
            ? $this->files->sortBy('position')->first()
            : $this->files()->orderBy('position')->first();

        return $file?->full_path;
    }

    public function slugRecord(): MorphOne
    {
        return $this->morphOne(Slug::class, 'sluggable');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'custom_page_category_map')
            ->withPivot(['sort_order'])
            ->orderByPivot('sort_order');
    }

    public function pageCategories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CustomPageCategory::class);
    }

    public function files(): MorphMany
    {
        return $this->morphMany(File::class, 'model');
    }

    public function primaryImage(): MorphOne
    {
        return $this->morphOne(File::class, 'model')
            ->where('is_primary', true)
            ->orderBy('position');
    }
}
