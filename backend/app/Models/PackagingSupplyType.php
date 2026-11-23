<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Admin-managed packaging type (client item #12). `code` is a stable, immutable key that is
 * also mirrored into packaging_supplies.type for backward compatibility.
 */
class PackagingSupplyType extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'code',
        'name_en',
        'name_ar',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function supplies(): HasMany
    {
        return $this->hasMany(PackagingSupply::class, 'packaging_supply_type_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name_en');
    }

    public function localizedName(): string
    {
        return localized($this);
    }

    public function badgeClass(): string
    {
        return match ($this->code) {
            'box' => 'bg-blue-100 text-blue-800',
            'bag' => 'bg-green-100 text-green-800',
            'tape' => 'bg-orange-100 text-orange-800',
            'label' => 'bg-purple-100 text-purple-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }
}
