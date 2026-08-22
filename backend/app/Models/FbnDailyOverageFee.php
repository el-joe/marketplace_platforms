<?php

namespace App\Models;

use App\Enums\FbnStorageFeeStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FbnDailyOverageFee extends Model
{
    use HasUuids;

    protected $table = 'fbn_daily_overage_fees';

    protected $fillable = [
        'vendor_id',
        'warehouse_inventory_id',
        'warehouse_id',
        'received_at',
        'free_period_ends_at',
        'fee_date',
        'units',
        'fee_per_unit',
        'total_fee',
        'currency',
        'status',
    ];

    protected $casts = [
        'status' => FbnStorageFeeStatus::class,
        'received_at' => 'date',
        'free_period_ends_at' => 'date',
        'fee_date' => 'date',
        'units' => 'integer',
        'fee_per_unit' => 'integer',
        'total_fee' => 'integer',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function warehouseInventory(): BelongsTo
    {
        return $this->belongsTo(WarehouseInventory::class, 'warehouse_inventory_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            FbnStorageFeeStatus::Pending => 'warning',
            FbnStorageFeeStatus::Invoiced => 'primary',
            FbnStorageFeeStatus::Paid => 'success',
            default => 'secondary',
        };
    }
}
