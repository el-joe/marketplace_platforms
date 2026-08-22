<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorExceptionalZoneAlert extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $table = 'vendor_exceptional_zone_alerts';

    protected $fillable = [
        'vendor_id',
        'warehouse_id',
        'city_ids',
        'carrier_id',
        'reported_carrier_fee',
        'currency',
        'vendor_note',
        'status',
        'admin_note',
        'reviewed_by_admin_id',
        'reviewed_at',
    ];

    protected $casts = [
        'city_ids' => 'array',
        'reported_carrier_fee' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(ShippingCarrier::class, 'carrier_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by_admin_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(VendorExceptionalZoneAlertResult::class, 'alert_id');
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    public function getCitiesAttribute(): Collection
    {
        return City::whereIn('id', $this->city_ids ?? [])->get();
    }

    public function getCitiesGroupedByZoneAttribute(): Collection
    {
        return $this->cities
            ->groupBy(fn (City $city) => $city->shipping_zone_id ?? 'unzoned');
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'amber',
            'accepted' => 'green',
            'rejected' => 'gray',
        };
    }

    // ── Status helpers ────────────────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }
}
