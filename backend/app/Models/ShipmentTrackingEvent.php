<?php

namespace App\Models;

use App\Enums\ShipmentTrackingEventStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentTrackingEvent extends Model
{
    protected $fillable = [
        'shipment_id',
        'status',
        'description',
        'location',
        'occurred_at',
        'raw_payload',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'status' => ShipmentTrackingEventStatus::class,
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
