<?php

namespace App\Models;

use App\Enums\CodSettlementDiscrepancyResolution;
use App\Enums\DeliveryAgentCodSettlementStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryAgentCodSettlement extends Model
{
    use HasUuids;

    protected $fillable = [
        'agent_id',
        'period_start',
        'period_end',
        'total_cod_collected',
        'total_earnings_owed',
        'net_to_remit',
        'status',
        'settled_at',
        'notes',
        'has_collection_discrepancy',
        'discrepancy_notes',
        'discrepancy_amount',
        'discrepancy_resolution',
    ];

    /** @var int Base currency unit (BIGINT) for money fields renamed in this model */
    protected $casts = [
        'period_start'               => 'date',
        'period_end'                 => 'date',
        'total_cod_collected'  => 'integer',
        'total_earnings_owed'  => 'integer',
        'net_to_remit'         => 'integer',
        'discrepancy_amount'   => 'integer',
        'has_collection_discrepancy' => 'boolean',
        'settled_at'                 => 'datetime',
        'status'                     => DeliveryAgentCodSettlementStatus::class,
        'discrepancy_resolution'     => CodSettlementDiscrepancyResolution::class,
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(DeliveryAgent::class, 'agent_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DeliveryAssignment::class, 'cod_settlement_id');
    }

    public function earnings(): HasMany
    {
        return $this->hasMany(DeliveryAgentEarning::class, 'agent_id', 'agent_id')
            ->whereBetween('created_at', [$this->period_start, $this->period_end]);
    }
}
