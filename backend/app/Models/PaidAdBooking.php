<?php

namespace App\Models;

use App\Enums\PaidAdBookingStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaidAdBooking extends Model
{
    use HasUuids;

    /** @var int Base currency unit (BIGINT) for money fields renamed in this model */
    protected $casts = [
        'status' => PaidAdBookingStatus::class,
    ];

    protected $fillable = [
        'booking_reference',
        'paid_ad_slot_id',
        'vendor_id',
        'country_id',
        'pricing_model',
        'booked_from',
        'booked_until',
        'agreed_rate',
        'currency',
        'status',
        'rejection_reason',
        'payment_status',
        'payment_transaction_id',
        'invoiced_at',
        'paid_at',
        'impressions_delivered',
        'clicks_delivered',
        'cpm_impressions_billed',
        'total_charged',
        'approved_by_admin_id',
        'approved_at',
        'notes',
    ];

    public function slot(): BelongsTo
    {
        return $this->belongsTo(PaidAdSlot::class, 'paid_ad_slot_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class);
    }

    public function approvedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by_admin_id');
    }

    public function creatives(): HasMany
    {
        return $this->hasMany(PaidAdCreative::class, 'paid_ad_booking_id');
    }
}
