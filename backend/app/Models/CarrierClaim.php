<?php

namespace App\Models;

use App\Enums\CarrierClaimStatus;
use App\Enums\CarrierClaimType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarrierClaim extends Model
{
    use HasUuids;

    protected $fillable = [
        'claim_number',
        'shipment_id',
        'shipping_company_id',
        'delivery_agent_id',
        'claim_type',
        'description',
        'claimed_amount',
        'evidence_files',
        'status',
        'resolution_notes',
        'compensated_amount',
        'resolved_by_admin_id',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'evidence_files'           => 'array',
            'claimed_amount'     => 'integer',
            'compensated_amount' => 'integer',
            'resolved_at'              => 'datetime',
            'status'                   => CarrierClaimStatus::class,
            'claim_type'               => CarrierClaimType::class,
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function shippingCompany(): BelongsTo
    {
        return $this->belongsTo(ShippingCompany::class);
    }

    public function deliveryAgent(): BelongsTo
    {
        return $this->belongsTo(DeliveryAgent::class);
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'resolved_by_admin_id');
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function getClaimedAmountFormattedAttribute(): string
    {
        return number_format($this->claimed_amount, 2);
    }

    public function getCompensatedAmountFormattedAttribute(): string
    {
        if ($this->compensated_amount === null) {
            return '—';
        }
        return number_format($this->compensated_amount, 2);
    }

    public function isResolved(): bool
    {
        return in_array($this->status, [
            CarrierClaimStatus::Approved,
            CarrierClaimStatus::Rejected,
            CarrierClaimStatus::Compensated,
        ], true);
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            CarrierClaimStatus::Submitted    => 'bg-yellow-100 text-yellow-800',
            CarrierClaimStatus::UnderReview  => 'bg-blue-100 text-blue-800',
            CarrierClaimStatus::Approved     => 'bg-green-100 text-green-800',
            CarrierClaimStatus::Compensated  => 'bg-emerald-100 text-emerald-800',
            CarrierClaimStatus::Rejected     => 'bg-red-100 text-red-800',
            default                          => 'bg-gray-100 text-gray-800',
        };
    }
}
