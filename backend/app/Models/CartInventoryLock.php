<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartInventoryLock extends Model
{
    protected $fillable = [
        'cart_id',
        'warehouse_inventory_id',
        'vendor_listing_id',
        'quantity',
        'expires_at',
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function warehouseInventory(): BelongsTo
    {
        return $this->belongsTo(WarehouseInventory::class);
    }

    public function vendorListing(): BelongsTo
    {
        return $this->belongsTo(VendorListing::class);
    }
}
