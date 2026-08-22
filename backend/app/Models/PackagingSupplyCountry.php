<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackagingSupplyCountry extends Model
{
    use HasUuids;

    protected $table = 'packaging_supply_countries';

    protected $fillable = [
        'packaging_supply_id',
        'country_id',
        'unit_cost',
        'stock_available',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'unit_cost' => 'integer',
            'stock_available' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function supply(): BelongsTo
    {
        return $this->belongsTo(PackagingSupply::class, 'packaging_supply_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function getUnitCostFormattedAttribute(): string
    {
        if ($this->unit_cost === 0) {
            return 'Free';
        }

        return number_format($this->unit_cost) . ' ' . ($this->country?->currency_code ?? config('app.currency', 'SAR'));
    }

    public function isFree(): bool
    {
        return $this->unit_cost === 0;
    }

    public function isInStock(): bool
    {
        return $this->stock_available === null || $this->stock_available > 0;
    }
}
