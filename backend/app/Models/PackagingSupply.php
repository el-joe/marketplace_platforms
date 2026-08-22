<?php

namespace App\Models;

use App\Enums\PackagingSupplyType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PackagingSupply extends Model
{
    use HasUuids;

    protected $fillable = [
        'name_en',
        'name_ar',
        'type',
        'size',
        'description_en',
        'description_ar',
        'unit_cost',
        'currency',
        'stock_available',
        'is_active',
        'sort_order',
        'image_path',
    ];

    protected function casts(): array
    {
        return [
            'unit_cost'  => 'integer',
            'stock_available'  => 'integer',
            'is_active'        => 'boolean',
            'sort_order'       => 'integer',
            'type'             => PackagingSupplyType::class,
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    public function scopeInStock($query)
    {
        return $query->where(fn ($q) => $q->whereNull('stock_available')
            ->orWhere('stock_available', '>', 0));
    }

    public function requestItems(): HasMany
    {
        return $this->hasMany(PackagingSupplyRequestItem::class);
    }

    /**
     * Per-country pricing/stock/availability overrides. This is the
     * authoritative source of pricing for partners; unit_cost/currency/
     * stock_available on this model remain as legacy fallback columns.
     */
    public function countryPricing(): HasMany
    {
        return $this->hasMany(PackagingSupplyCountry::class, 'packaging_supply_id');
    }

    public function pricingForCountry(string $countryId): ?PackagingSupplyCountry
    {
        if ($this->relationLoaded('countryPricing')) {
            return $this->countryPricing->firstWhere('country_id', $countryId);
        }

        return $this->countryPricing()->where('country_id', $countryId)->first();
    }

    public function isAvailableInCountry(string $countryId): bool
    {
        $pricing = $this->pricingForCountry($countryId);

        return $pricing !== null && $pricing->is_active;
    }

    public function getUnitCostFormattedAttribute(): string
    {
        if ($this->unit_cost === 0) {
            return 'Free';
        }

        return number_format($this->unit_cost) . ' ' . ($this->currency ?? config('app.currency', 'SAR'));
    }

    public function isFree(): bool
    {
        return $this->unit_cost === 0;
    }

    public function typeBadgeClass(): string
    {
        return match ($this->type) {
            PackagingSupplyType::Box        => 'bg-blue-100 text-blue-800',
            PackagingSupplyType::Bag        => 'bg-green-100 text-green-800',
            PackagingSupplyType::Tape       => 'bg-orange-100 text-orange-800',
            PackagingSupplyType::Label      => 'bg-purple-100 text-purple-800',
            PackagingSupplyType::BubbleWrap => 'bg-gray-100 text-gray-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }
}
