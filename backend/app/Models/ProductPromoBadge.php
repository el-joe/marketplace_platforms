<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single message rotated through the PDP/listing-card "AnimatedBadge"
 * (e.g. "Free next-day delivery" with a truck icon). Multiple active rows
 * per product are shown in `sort_order`, cycling every few seconds on the
 * frontend — see docs/plans/dynamic-badges-and-classified-actions.md Task A.
 */
class ProductPromoBadge extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'product_id',
        'vendor_listing_id',
        'admin_listing_id',
        'marketer_listing_id',
        'label_en',
        'label_ar',
        'icon_key',
        'color_hex',
        'text_color_hex',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active'  => 'boolean',
    ];

    /** Product-level (admin-managed) badges: not attached to any listing. */
    public function scopeProductLevel($query)
    {
        return $query->whereNull('vendor_listing_id')->whereNull('admin_listing_id')->whereNull('marketer_listing_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
