<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorExceptionalZoneAlertResult extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $table = 'vendor_exceptional_zone_alert_results';

    protected $fillable = [
        'alert_id',
        'shipping_zone_id',
        'shipping_method_id',
        'created_subsidy_id',
        'created_exceptional_zone_id',
    ];

    public function alert(): BelongsTo
    {
        return $this->belongsTo(VendorExceptionalZoneAlert::class, 'alert_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class, 'shipping_method_id');
    }

    public function subsidy(): BelongsTo
    {
        return $this->belongsTo(PlatformShippingSubsidy::class, 'created_subsidy_id');
    }

    public function exceptionalZone(): BelongsTo
    {
        return $this->belongsTo(WarehouseExceptionalZone::class, 'created_exceptional_zone_id');
    }
}
