<?php

namespace App\Models;

use App\Enums\ShipmentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Shipment extends Model
{
    use HasUuids;
    protected $fillable = [
        'sub_order_id',
        'carrier_id',
        'tracking_number',
        'awb_label_url',
        'weight_grams',
        'dimensions',
        'shipping_cost_actual',
        'status',
        'picked_up_at',
        'delivered_at',
        'delivery_otp',
    ];

    protected function casts(): array
    {
        return [
            'status' => ShipmentStatus::class,
        ];
    }

    public function subOrder(): BelongsTo
    {
        return $this->belongsTo(SubOrder::class);
    }

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(ShippingCarrier::class, 'carrier_id');
    }

    public function trackingEvents(): HasMany
    {
        return $this->hasMany(ShipmentTrackingEvent::class);
    }

    public function files(): MorphMany
    {
        return $this->morphMany(File::class, 'model');
    }

    public function carrierClaims(): HasMany
    {
        return $this->hasMany(CarrierClaim::class);
    }

    public function deliveryAssignment(): HasOne
    {
        return $this->hasOne(DeliveryAssignment::class);
    }
}
