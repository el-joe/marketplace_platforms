<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseVendorLimit extends Model
{
    use HasUuids;

    protected $fillable = [
        'warehouse_id',
        'vendor_id',
        'limit_type',
        'max_quantity',
        'max_capacity_m3',
    ];

    protected $casts = [
        'max_quantity' => 'integer',
        'max_capacity_m3' => 'decimal:2',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function isQuantityBased(): bool
    {
        return $this->limit_type === 'quantity';
    }

    public function isCapacityBased(): bool
    {
        return $this->limit_type === 'capacity';
    }
}
