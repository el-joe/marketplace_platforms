<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Client feature request doc, section 6 ("تثبيت أول سعر للمنتج / Price History").
 *
 * One row per price ever set on a vendor listing: the first one at
 * creation (source='initial', incl. drafts) and every subsequent change
 * (source='update'). Written by VendorListingObserver — never written to
 * directly elsewhere so the log stays a faithful history.
 */
class ProductPriceHistory extends Model
{
    use HasUuids;

    protected $table = 'product_price_history';

    protected $fillable = [
        'vendor_listing_id',
        'price',
        'recorded_at',
        'source',
        'recorded_by',
    ];

    protected $casts = [
        'price' => 'integer',
        'recorded_at' => 'datetime',
    ];

    public function vendorListing(): BelongsTo
    {
        return $this->belongsTo(VendorListing::class);
    }
}
