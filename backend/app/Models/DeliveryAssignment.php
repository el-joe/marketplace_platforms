<?php

namespace App\Models;

use App\Enums\DeliveryAssignmentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryAssignment extends Model
{
    use HasUuids;

    public const STATUS_ASSIGNED = DeliveryAssignmentStatus::Assigned;
    public const STATUS_ACCEPTED = DeliveryAssignmentStatus::Accepted;
    public const STATUS_PICKED_UP = DeliveryAssignmentStatus::PickedUp;
    public const STATUS_DELIVERED = DeliveryAssignmentStatus::Delivered;
    public const STATUS_FAILED = DeliveryAssignmentStatus::Failed;

    protected $fillable = [
        'shipment_id',
        'sub_order_id',
        'agent_id',
        'status',
        'assigned_at',
        'accepted_at',
        'picked_up_at',
        'delivered_at',
        'failed_at',
        'failure_reason',
        'failure_notes',
        'delivery_otp',
        'otp_attempts',
        'otp_verified',
        'proof_file_id',
        'agent_notes',
        'pickup_latitude',
        'pickup_longitude',
        'delivery_latitude',
        'delivery_longitude',
        'customer_rating',
        'cod_amount_collected',
        'discrepancy_note',
        'cod_settlement_id',
        'customer_rejection_reason',
        'rejected_by_customer_at',
        'rejection_reason_mandatory',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'accepted_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
            'rejected_by_customer_at' => 'datetime',
            'rejection_reason_mandatory' => 'boolean',
            'otp_verified' => 'boolean',
            'otp_attempts' => 'integer',
            'customer_rating' => 'integer',
            'pickup_latitude' => 'float',
            'pickup_longitude' => 'float',
            'delivery_latitude' => 'float',
            'delivery_longitude' => 'float',
            'status' => DeliveryAssignmentStatus::class,
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function subOrder(): BelongsTo
    {
        return $this->belongsTo(SubOrder::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(DeliveryAgent::class, 'agent_id');
    }

    public function proofFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'proof_file_id');
    }

    public function codSettlement(): BelongsTo
    {
        return $this->belongsTo(DeliveryAgentCodSettlement::class, 'cod_settlement_id');
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    public function getDurationMinutesAttribute(): ?int
    {
        if ($this->assigned_at && $this->delivered_at) {
            return (int) $this->assigned_at->diffInMinutes($this->delivered_at);
        }
        return null;
    }
}
