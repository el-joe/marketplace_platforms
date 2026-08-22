<?php

namespace App\Models;

use App\Enums\DeliveryAgentPayoutStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryAgentPayout extends Model
{
    use HasUuids;

    protected $fillable = [
        'payout_number',
        'agent_id',
        'period_start',
        'period_end',
        'total_deliveries',
        'gross_earnings',
        'deductions',
        'net_amount',
        'currency',
        'status',
        'payment_method',
        'payment_reference',
        'approved_by_admin_id',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'gross_earnings' => 'integer',
            'deductions' => 'integer',
            'net_amount' => 'integer',
            'processed_at' => 'datetime',
            'status' => DeliveryAgentPayoutStatus::class,
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function agent(): BelongsTo
    {
        return $this->belongsTo(DeliveryAgent::class, 'agent_id');
    }

    public function approvedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by_admin_id');
    }
}
