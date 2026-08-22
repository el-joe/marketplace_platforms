<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BlogCategory extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'parent_id',
        'name_en',
        'name_ar',
        'slug',
        'description_en',
        'description_ar',
        'cover_image_path',
        'color_hex',
        'icon_name',
        'seo_title_en',
        'seo_title_ar',
        'seo_description_en',
        'seo_description_ar',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(BlogCategory::class, 'parent_id');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(BlogPost::class, 'blog_category_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }
}
