<?php

namespace App\Models;

use App\Enums\DeliveryAgentDocumentStatus;
use App\Enums\DeliveryAgentDocumentType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryAgentDocument extends Model
{
    use HasUuids;

    protected $fillable = [
        'agent_id',
        'document_type',
        'file_path',
        'status',
        'verified_by_admin_id',
        'verified_at',
        'expires_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'expires_at' => 'date',
            'status' => DeliveryAgentDocumentStatus::class,
            'document_type' => DeliveryAgentDocumentType::class,
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function agent(): BelongsTo
    {
        return $this->belongsTo(DeliveryAgent::class, 'agent_id');
    }

    public function verifiedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'verified_by_admin_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function getLabelAttribute(): string
    {
        return $this->document_type->label();
    }
}
