<?php

namespace App\Models;

use App\Enums\InventoryTransferStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryTransfer extends Model
{
    use HasUuids;

    protected $fillable = [
        'transfer_number',
        'source_warehouse_id',
        'destination_warehouse_id',
        'vendor_id',
        'status',
        'initiated_by_user_id',
        'carrier',
        'tracking_number',
        'expected_arrival_date',
        'shipped_at',
        'received_at',
        'notes',
    ];

    protected $casts = [
        'shipped_at' => 'datetime',
        'received_at' => 'datetime',
        'expected_arrival_date' => 'date',
        'status' => InventoryTransferStatus::class,
    ];

    // ─── Relationships ─────────────────────────────────────────────────────────

    public function sourceWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'source_warehouse_id');
    }

    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'initiated_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryTransferItem::class);
    }
}
