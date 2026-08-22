<?php

namespace App\Models;

use App\Enums\PaidAdSlotPricingModel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaidAdSlot extends Model
{
    use HasUuids;

    /** @var int Base currency unit (BIGINT) for money fields renamed in this model */
    protected $casts = [
        'pricing_model' => PaidAdSlotPricingModel::class,
    ];

    protected $fillable = [
        'placement_definition_id',
        'country_id',
        'name',
        'slot_code',
        'pricing_model',
        'base_rate',
        'currency',
        'min_booking_days',
        'max_booking_days',
        'is_available',
        'requires_approval',
        'min_seller_tier',
        'notes_for_vendors',
        'created_by_admin_id',
    ];

    public function placementDefinition(): BelongsTo
    {
        return $this->belongsTo(BannerPlacementDefinition::class, 'placement_definition_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(PaidAdBooking::class, 'paid_ad_slot_id');
    }
}
