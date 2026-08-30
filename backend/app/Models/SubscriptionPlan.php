<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    use HasUuids;

    protected $fillable = [
        'name_en',
        'name_ar',
        'description_en',
        'description_ar',
        'price',
        'currency',
        'billing_cycle',
        'max_listings',
        'free_shipping_included',
        'commission_discount_pct',
        'features',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'max_listings' => 'integer',
            'free_shipping_included' => 'boolean',
            'commission_discount_pct' => 'decimal:2',
            'features' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function subscriptions(): HasMany
    {
        return $this->hasMany(VendorSubscription::class, 'plan_id');
    }

    // ── Accessors / Helpers ───────────────────────────────────────────────────

    public function priceFormatted(): string
    {
        return number_format($this->price, 2) . ' ' . $this->currency;
    }

    public function hasUnlimitedListings(): bool
    {
        return is_null($this->max_listings);
    }

    public function listingsLabel(): string
    {
        return $this->hasUnlimitedListings()
            ? 'Unlimited'
            : number_format($this->max_listings) . ' listings';
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('price');
    }
}
