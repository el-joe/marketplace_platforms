<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseExceptionalZone extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'warehouse_id',
        'destination_zone_id',
        'carrier_id',
        'is_active',
        'source_alert_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function destinationZone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'destination_zone_id');
    }

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(ShippingCarrier::class, 'carrier_id');
    }

    public function sourceAlert(): BelongsTo
    {
        return $this->belongsTo(VendorExceptionalZoneAlert::class, 'source_alert_id');
    }
}
