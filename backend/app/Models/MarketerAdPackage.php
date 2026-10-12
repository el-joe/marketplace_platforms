<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketerAdPackage extends Model
{
    use HasUuids;

    protected $fillable = ['name_ar', 'name_en', 'description_ar', 'price', 'currency', 'vat_pct', 'target_type',
        'duration_days', 'features', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['price' => \App\Casts\DecimalMoney::class, 'vat_pct' => 'integer', 'duration_days' => 'integer',
            'features' => 'array', 'is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function scopeOrdered($q)
    {
        return $q->orderBy('sort_order')->orderBy('price');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(MarketerAdPackageSubscription::class, 'package_id');
    }

    public function getNameAttribute(): string
    {
        return app()->getLocale() === 'ar' ? $this->name_ar : ($this->name_en ?: $this->name_ar);
    }

    /** VAT is percentage math, rounded half up to 2 decimals. */
    public static function vatFor(int|float $price, int|float $vatPct): int|float
    {
        return Money::number(round((float) $price * (float) $vatPct / 100, 2));
    }

    public function getVatAmountAttribute(): int|float
    {
        return self::vatFor((float) $this->price, (float) $this->vat_pct);
    }

    public function getTotalAttribute(): int|float
    {
        return Money::number((float) $this->price + (float) $this->vat_amount);
    }
}
