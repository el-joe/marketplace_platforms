<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Brand extends Model
{
    use HasUuids, SoftDeletes;
    protected $fillable = [
        'name_ar',
        'name_en',
        'slug',
        'logo_media_id',
        'description_ar',
        'description_en',
        'website_url',
        'is_verified',
        'is_restricted',
        'is_active',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'is_restricted' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Categories this brand has active products in (indirect, via products.category_id).
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'products', 'brand_id', 'category_id')
            ->where('products.status', 'active')
            ->distinct();
    }

    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo_media_id) {
            return null;
        }

        return str_contains($this->logo_media_id, '/') ? Storage::url($this->logo_media_id) : $this->logo_media_id;
    }
}
