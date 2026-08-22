<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentLocationHistory extends Model
{
    protected $table = 'agent_location_history';

    // High-write append-only log; Laravel timestamps not used — recorded_at is the canonical time.
    public $timestamps = false;

    protected $fillable = [
        'agent_id',
        'latitude',
        'longitude',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(DeliveryAgent::class, 'agent_id');
    }
}
