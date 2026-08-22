<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WishlistItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'wishlist_group_id', 'customer_id',
        'vendor_listing_id', 'admin_listing_id',
        'product_variant_id', 'added_at',
    ];

    protected $casts = [
        'added_at' => 'datetime',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(WishlistGroup::class, 'wishlist_group_id');
    }

    public function vendorListing(): BelongsTo
    {
        return $this->belongsTo(VendorListing::class);
    }

    public function adminListing(): BelongsTo
    {
        return $this->belongsTo(AdminListing::class, 'admin_listing_id');
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
