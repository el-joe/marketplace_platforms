<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdSupportCollection extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'parent_id',
        'name',
        'name_en',
        'name_ar',
        'slug',
        'description',
        'description_en',
        'description_ar',
        'icon',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function localizedName(): string
    {
        return session('locale', 'ar') === 'ar'
            ? ($this->name_ar ?: $this->getRawOriginal('name'))
            : ($this->name_en ?: $this->getRawOriginal('name'));
    }

    public function localizedDescription(): ?string
    {
        return session('locale', 'ar') === 'ar'
            ? ($this->description_ar ?: $this->getRawOriginal('description'))
            : ($this->description_en ?: $this->getRawOriginal('description'));
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(AdSupportCollection::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(AdSupportCollection::class, 'parent_id');
    }

    public function articles(): HasMany
    {
        return $this->hasMany(AdSupportArticle::class, 'ad_support_collection_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Published article count across this collection and, if top-level, its sub-collections.
     */
    public function totalPublishedArticleCount(): int
    {
        $count = $this->articles()->where('status', 'published')->count();

        if ($this->relationLoaded('children')) {
            $count += $this->children->sum(fn (self $child) => $child->articles()->where('status', 'published')->count());
        } else {
            $count += AdSupportArticle::whereIn('ad_support_collection_id', $this->children()->pluck('id'))
                ->where('status', 'published')
                ->count();
        }

        return $count;
    }
}
