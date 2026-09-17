<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * enhancement.md P-13: the exact warehouse_inventory row (and quantity)
 * an order_item's stock was reserved from, so release/commit/return never
 * have to re-derive "the" row from listing + sub_order.warehouse_id.
 */
class OrderItemAllocation extends Model
{
    use HasUuids;

    protected $fillable = [
        'order_item_id',
        'warehouse_inventory_id',
        'quantity',
        'status',
    ];

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function warehouseInventory(): BelongsTo
    {
        return $this->belongsTo(WarehouseInventory::class);
    }
}
