<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformShippingSubsidy extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected static function booted(): void
    {
        static::saved(fn () => \App\Services\ShippingSubsidyService::flushCache());
        static::deleted(fn () => \App\Services\ShippingSubsidyService::flushCache());
    }

    protected $fillable = [
        'warehouse_id',
        'carrier_id',
        'shipping_zone_id',
        'shipping_method_id',
        'currency',
        'subsidy_cap',
        'split_type',
        'vendor_share_pct',
        'vendor_fixed_amount',
        'admin_fixed_amount',
        'max_subsidy_weight_grams',
        'is_active',
        'created_by_admin_id',
    ];

    protected $casts = [
        'subsidy_cap' => 'integer',
        'vendor_share_pct' => 'integer',
        'vendor_fixed_amount' => 'integer',
        'admin_fixed_amount' => 'integer',
        'max_subsidy_weight_grams' => 'integer',
        'is_active' => 'boolean',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(ShippingCarrier::class, 'carrier_id');
    }

    public function shippingZone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class);
    }

    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }
}
