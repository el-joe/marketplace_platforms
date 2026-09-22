<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The base (admin-set) price for open-market (classified) listings in a
 * given classified category, plus whether the marketer who owns a listing
 * in that category is allowed to override it with their own price, bounded
 * by min_price/max_price when set.
 */
class OpenMarketListingPrice extends Model
{
    use HasUuids;

    protected $fillable = [
        'classified_category_id',
        'base_price',
        'allow_marketer_override',
        'min_price',
        'max_price',
        'updated_by_admin_id',
    ];

    protected $casts = [
        'base_price' => 'integer',
        'allow_marketer_override' => 'boolean',
        'min_price' => 'integer',
        'max_price' => 'integer',
    ];

    public function classifiedCategory(): BelongsTo
    {
        return $this->belongsTo(ClassifiedCategory::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'updated_by_admin_id');
    }

    /**
     * Whether $price is within this rule's min_price/max_price bounds.
     * A null bound is treated as "no limit" on that side.
     */
    public function isPriceInBounds(int $price): bool
    {
        if ($this->min_price !== null && $price < $this->min_price) {
            return false;
        }

        if ($this->max_price !== null && $price > $this->max_price) {
            return false;
        }

        return true;
    }
}
