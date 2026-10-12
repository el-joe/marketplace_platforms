<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class CategoryCommissionTier extends Model
{
    use HasUuids;

    protected $fillable = [
        'category_id',
        'country_id',
        'price_from',
        'price_to',
        'commission_rate',
        'min_commission',
        'sort_order',
    ];

    protected $casts = [
        'price_from' => \App\Casts\DecimalMoney::class,
        'price_to' => \App\Casts\DecimalMoney::class,
        'commission_rate' => 'decimal:2',
        'min_commission' => \App\Casts\DecimalMoney::class,
        'sort_order' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Find the tier covering the given unit price.
     * price_from is inclusive, price_to is inclusive (NULL = open-ended / no upper bound).
     * Tiers must be sorted by price_from ASC. Returns null when none matches.
     *
     * @param  Collection<int, self>  $tiers
     */
    public static function resolveForUnitPrice(Collection $tiers, int|float $unitPrice): ?self
    {
        foreach ($tiers as $tier) {
            if ($unitPrice < $tier->price_from) {
                continue;
            }
            if ($tier->price_to !== null && $unitPrice > $tier->price_to) {
                continue;
            }

            return $tier;
        }

        return null;
    }
}
