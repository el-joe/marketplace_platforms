<?php

namespace App\Models;

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
        return ['price' => 'integer', 'vat_pct' => 'integer', 'duration_days' => 'integer',
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

    /** VAT is percentage math on integer amounts, rounded to nearest whole unit. */
    public static function vatFor(int $price, int $vatPct): int
    {
        return intdiv($price * $vatPct + 50, 100);
    }

    public function getVatAmountAttribute(): int
    {
        return self::vatFor((int) $this->price, (int) $this->vat_pct);
    }

    public function getTotalAttribute(): int
    {
        return (int) $this->price + $this->vat_amount;
    }
}
