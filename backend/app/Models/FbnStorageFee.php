<?php

namespace App\Models;

use App\Enums\FbnStorageFeeStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FbnStorageFee extends Model
{
    use HasUuids;

    protected $table = 'fbn_storage_fees';

    protected $fillable = [
        'vendor_id',
        'warehouse_inventory_id',
        'month',
        'units_stored',
        'rate_per_unit',
        'total_fee',
        'currency',
        'status',
    ];

    /** @var int Base currency unit (BIGINT) for money fields renamed in this model */
    protected $casts = [
        'status' => FbnStorageFeeStatus::class,
        'month' => 'date',
        'units_stored' => 'integer',
        'rate_per_unit' => 'integer',
        'total_fee' => 'integer',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function warehouseInventory(): BelongsTo
    {
        return $this->belongsTo(WarehouseInventory::class, 'warehouse_inventory_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', FbnStorageFeeStatus::Pending);
    }
    public function scopeInvoiced($query)
    {
        return $query->where('status', FbnStorageFeeStatus::Invoiced);
    }
    public function scopePaid($query)
    {
        return $query->where('status', FbnStorageFeeStatus::Paid);
    }

    public function scopeForMonth($query, string $month)
    {
        return $query->whereDate('month', $month);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function statusColor(): string
    {
        return match ($this->status) {
            FbnStorageFeeStatus::Pending => 'warning',
            FbnStorageFeeStatus::Invoiced => 'primary',
            FbnStorageFeeStatus::Paid => 'success',
            default => 'secondary',
        };
    }

    public function totalFormatted(): string
    {
        return number_format($this->total_fee / 100, 2) . ' ' . $this->currency;
    }

    public function monthLabel(): string
    {
        return $this->month?->format('F Y') ?? '—';
    }
}
