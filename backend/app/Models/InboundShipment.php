<?php

namespace App\Models;

use App\Enums\InboundShipmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class InboundShipment extends Model
{
    protected $fillable = [
        'shipment_code',
        'vendor_id',
        'warehouse_id',
        'country_id',
        'status',
        'carrier',
        'tracking_number',
        'expected_arrival_date',
        'arrived_at',
        'received_at',
        'received_by_admin_id',
        'notes',
        'vendor_listing_id',
        'expected_quantity',
        'received_quantity',
        'damaged_quantity',
        'condition_notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => InboundShipmentStatus::class,
            'expected_arrival_date' => 'date',
            'arrived_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function receivedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'received_by_admin_id');
    }

    public function files(): MorphMany
    {
        return $this->morphMany(File::class, 'model');
    }
}
