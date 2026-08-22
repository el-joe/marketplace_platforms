<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryTransferItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'inventory_transfer_id',
        'vendor_listing_id',
        'quantity_requested',
        'quantity_received',
        'damaged_quantity',
        'condition_notes',
    ];

    // ─── Relationships ───────────────────────────────────────────────────────

    public function inventoryTransfer(): BelongsTo
    {
        return $this->belongsTo(InventoryTransfer::class);
    }

    public function vendorListing(): BelongsTo
    {
        return $this->belongsTo(VendorListing::class, 'vendor_listing_id');
    }
}
