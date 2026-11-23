<?php

namespace App\Models;

use App\Casts\DecimalMoney;
use App\Support\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PackagingSupply extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'name_en',
        'name_ar',
        'type',
        'packaging_supply_type_id',
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
            'unit_cost' => DecimalMoney::class,
            'stock_available' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * `type` is the legacy code column; it is kept in sync with the selected
     * admin-managed type so anything still reading it sees the same code.
     */
    protected static function booted(): void
    {
        static::saving(function (PackagingSupply $supply) {
            if ($supply->isDirty('packaging_supply_type_id') && $supply->packaging_supply_type_id) {
                $supply->type = PackagingSupplyType::whereKey($supply->packaging_supply_type_id)->value('code') ?? $supply->type;
            }
        });
    }

    public function supplyType(): BelongsTo
    {
        return $this->belongsTo(PackagingSupplyType::class, 'packaging_supply_type_id');
    }

    /**
     * Localized type name; falls back to the legacy code when no type row is linked.
     */
    public function typeName(): string
    {
        return $this->supplyType?->localizedName() ?? (string) $this->type;
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

        return Money::format($this->unit_cost).' '.($this->currency ?? config('app.currency', 'SAR'));
    }

    public function isFree(): bool
    {
        return $this->unit_cost === 0;
    }

    public function typeBadgeClass(): string
    {
        return $this->supplyType?->badgeClass() ?? 'bg-gray-100 text-gray-800';
    }
}
