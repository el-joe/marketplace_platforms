<?php

namespace App\Models;

use App\Enums\DeliveryAgentShiftStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryAgentShift extends Model
{
    use HasUuids;

    protected $fillable = [
        'agent_id',
        'shift_date',
        'zone_id',
        'scheduled_start',
        'scheduled_end',
        'actual_start',
        'actual_end',
        'status',
        'total_deliveries',
        'total_earnings',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'shift_date' => 'date',
            'actual_start' => 'datetime',
            'actual_end' => 'datetime',
            'total_earnings' => 'integer',
            'status' => DeliveryAgentShiftStatus::class,
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function agent(): BelongsTo
    {
        return $this->belongsTo(DeliveryAgent::class, 'agent_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class, 'zone_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    public function getTotalEarningsAttribute(): float
    {
        return $this->total_earnings / 100;
    }

    public function getDurationMinutesAttribute(): ?int
    {
        if ($this->actual_start && $this->actual_end) {
            return (int) $this->actual_start->diffInMinutes($this->actual_end);
        }
        return null;
    }
}
