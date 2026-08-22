<?php

namespace App\Models;

use App\Enums\DeliveryAgentEarningStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryAgentEarning extends Model
{
    use HasUuids;

    protected $fillable = [
        'agent_id',
        'delivery_assignment_id',
        'order_id',
        'earning_type',
        'amount',
        'currency',
        'status',
        'paid_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'paid_at' => 'datetime',
            'status' => DeliveryAgentEarningStatus::class,
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function agent(): BelongsTo
    {
        return $this->belongsTo(DeliveryAgent::class, 'agent_id');
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(DeliveryAssignment::class, 'delivery_assignment_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
