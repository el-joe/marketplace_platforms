<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdPackage extends Model
{
    use HasUuids;

    protected $fillable = [
        'tier',
        'name_en',
        'name_ar',
        'description_en',
        'description_ar',
        'price_monthly',
        'currency',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price_monthly' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function subscriptions(): HasMany
    {
        return $this->hasMany(VendorAdSubscription::class);
    }

    // ── Accessors / Helpers ───────────────────────────────────────────────────

    public function isFeatured(): bool
    {
        return $this->tier === 'serious_featured';
    }

    public function priceFormatted(): string
    {
        return number_format($this->price_monthly, 2) . ' ' . $this->currency;
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('price_monthly');
    }
}
