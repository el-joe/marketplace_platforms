<?php

namespace App\Models;

use App\Enums\PackagingSupplyRequestStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PackagingSupplyRequest extends Model
{
    use HasUuids;

    protected $fillable = [
        'request_number',
        'vendor_id',
        'warehouse_id',
        'status',
        'total_cost',
        'delivery_fee',
        'currency',
        'notes',
        'approved_by_admin_id',
        'approved_at',
        'shipped_at',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'total_cost'   => 'integer',
            'delivery_fee' => 'integer',
            'approved_at'        => 'datetime',
            'shipped_at'         => 'datetime',
            'delivered_at'       => 'datetime',
            'status'           => PackagingSupplyRequestStatus::class,
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by_admin_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PackagingSupplyRequestItem::class, 'request_id');
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function getTotalCostFormattedAttribute(): string
    {
        return $this->total_cost === 0
            ? 'Free'
            : number_format($this->total_cost) . ' ' . ($this->currency ?? config('app.currency', 'SAR'));
    }

    public function getDeliveryFeeFormattedAttribute(): string
    {
        return $this->delivery_fee === 0
            ? 'Free'
            : number_format($this->delivery_fee) . ' ' . ($this->currency ?? config('app.currency', 'SAR'));
    }

    public function getGrandTotalAttribute(): int
    {
        return $this->total_cost + $this->delivery_fee;
    }

    public function getGrandTotalFormattedAttribute(): string
    {
        return number_format($this->grand_total) . ' ' . ($this->currency ?? config('app.currency', 'SAR'));
    }

    public function isPending(): bool
    {
        return $this->status === PackagingSupplyRequestStatus::Pending;
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            PackagingSupplyRequestStatus::Pending   => 'bg-yellow-100 text-yellow-800',
            PackagingSupplyRequestStatus::Approved  => 'bg-blue-100 text-blue-800',
            PackagingSupplyRequestStatus::Shipped   => 'bg-indigo-100 text-indigo-800',
            PackagingSupplyRequestStatus::Delivered => 'bg-green-100 text-green-800',
            PackagingSupplyRequestStatus::Rejected  => 'bg-red-100 text-red-800',
            default     => 'bg-gray-100 text-gray-800',
        };
    }

    public static function generateRequestNumber(): string
    {
        return 'PKG-' . strtoupper(substr(uniqid(), -8));
    }
}
