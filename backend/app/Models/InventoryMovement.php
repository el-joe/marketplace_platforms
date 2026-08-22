<?php

namespace App\Models;

use App\Enums\InventoryMovementReferenceType;
use App\Enums\InventoryMovementType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'warehouse_inventory_id',
        'movement_type',
        'quantity_delta',
        'quantity_after',
        'reference_type',
        'reference_id',
        'reason',
        'created_by_user_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'movement_type' => InventoryMovementType::class,
        'reference_type' => InventoryMovementReferenceType::class,
    ];

    // Append-only: forbid updates and deletes
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model): void {
            $model->created_at = now();
        });

        static::updating(function (): void {
            throw new \RuntimeException('InventoryMovement records are immutable and cannot be updated.');
        });

        static::deleting(function (): void {
            throw new \RuntimeException('InventoryMovement records are immutable and cannot be deleted.');
        });
    }

    // ─── Relationships ─────────────────────────────────────────────────────────

    public function warehouseInventory(): BelongsTo
    {
        return $this->belongsTo(WarehouseInventory::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_user_id');
    }
}
