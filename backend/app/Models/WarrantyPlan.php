<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use App\Services\WarrantyPlanService;
use Illuminate\Support\Facades\Storage;

class WarrantyPlan extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::saved(fn () => WarrantyPlanService::flushCache());
        static::deleted(fn () => WarrantyPlanService::flushCache());
    }

    protected function casts(): array
    {
        return [
            'features_en' => 'array',
            'features_ar' => 'array',
            'country_ids' => 'array',
            'is_active' => 'boolean',
            'price' => 'integer',
            'price_pct' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function resolvePrice(int $listingPrice): int
    {
        if ($this->price_type === 'percentage' && $this->price_pct !== null) {
            return (int) floor($listingPrice * (float) $this->price_pct / 100);
        }

        return $this->price;
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? Storage::url($this->image_path) : null;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(WarrantyPurchase::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForCategory(Builder $query, string $categoryId): Builder
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeForCountry(Builder $query, string $countryId): Builder
    {
        return $query->where(function (Builder $q) use ($countryId) {
            $q->whereNull('country_ids')
                ->orWhereJsonContains('country_ids', $countryId);
        });
    }

    public function getDurationLabelAttribute(): string
    {
        return match ($this->duration_months) {
            1 => '1 month',
            6 => '6 months',
            12 => '1 year',
            24 => '2 years',
            default => "{$this->duration_months} months",
        };
    }

    public function getLocalizedNameAttribute(): ?string
    {
        return app()->getLocale() === 'ar' ? $this->name_ar : $this->name_en;
    }

    public function getLocalizedFeaturesAttribute(): ?array
    {
        return app()->getLocale() === 'ar' ? $this->features_ar : $this->features_en;
    }
}
