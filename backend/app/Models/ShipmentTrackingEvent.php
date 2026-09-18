<?php

namespace App\Models;

use App\Enums\ShipmentTrackingEventStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentTrackingEvent extends Model
{
    use HasUuids;

    protected $fillable = [
        'shipment_id',
        'carrier_id',
        'external_tracking_number',
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

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(ShippingCarrier::class, 'carrier_id');
    }
}
