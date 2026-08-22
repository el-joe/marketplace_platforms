<?php

namespace App\Models;

use App\Enums\FbnInboundRequestStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class FbnInboundRequest extends Model
{
    use HasUuids;

    protected $table = 'fbn_inbound_requests';

    protected $fillable = [
        'request_number',
        'vendor_id',
        'vendor_listing_id',
        'warehouse_id',
        'quantity_requested',
        'quantity_received',
        'status',
        'admin_approved_by',
        'approved_at',
        'expected_arrival',
        'tracking_number',
        'rejection_reason',
        'vendor_notes',
    ];

    protected $casts = [
        'status' => FbnInboundRequestStatus::class,
        'approved_at' => 'datetime',
        'expected_arrival' => 'date',
        'quantity_requested' => 'integer',
        'quantity_received' => 'integer',
    ];

    // ── Boot: auto-generate request_number ───────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->request_number)) {
                $year = now()->year;
                $seq = DB::table('fbn_inbound_requests')
                    ->whereYear('created_at', $year)
                    ->count() + 1;
                $model->request_number = 'FBN-' . $year . '-' . str_pad($seq, 5, '0', STR_PAD_LEFT);
            }
        });
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function vendorListing(): BelongsTo
    {
        return $this->belongsTo(VendorListing::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function approvedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_approved_by');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeDraft($query)
    {
        return $query->where('status', FbnInboundRequestStatus::Draft);
    }
    public function scopeSubmitted($query)
    {
        return $query->where('status', FbnInboundRequestStatus::Submitted);
    }
    public function scopeApproved($query)
    {
        return $query->where('status', FbnInboundRequestStatus::Approved);
    }
    public function scopeReceived($query)
    {
        return $query->where('status', FbnInboundRequestStatus::Received);
    }
    public function scopePendingApproval($query)
    {
        return $query->whereIn('status', [FbnInboundRequestStatus::Submitted]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function statusColor(): string
    {
        return match ($this->status) {
            FbnInboundRequestStatus::Draft => 'secondary',
            FbnInboundRequestStatus::Submitted => 'warning',
            FbnInboundRequestStatus::Approved => 'primary',
            FbnInboundRequestStatus::Shipped => 'info',
            FbnInboundRequestStatus::Received => 'success',
            FbnInboundRequestStatus::Rejected => 'danger',
            default => 'secondary',
        };
    }

    public function statusLabel(): string
    {
        return $this->status->label();
    }

    public function canBeApproved(): bool
    {
        return $this->status === FbnInboundRequestStatus::Submitted;
    }
    public function canBeRejected(): bool
    {
        return in_array($this->status, [FbnInboundRequestStatus::Submitted, FbnInboundRequestStatus::Approved], true);
    }
    public function canMarkShipped(): bool
    {
        return $this->status === FbnInboundRequestStatus::Approved;
    }
    public function canMarkReceived(): bool
    {
        return $this->status === FbnInboundRequestStatus::Shipped;
    }
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [FbnInboundRequestStatus::Draft, FbnInboundRequestStatus::Submitted], true);
    }
}
