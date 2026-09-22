<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Admin-created invitation for vendors/marketers to pay a participation fee
 * to join a coupon (client feature request #3.2). `coupon` is nullable
 * while the invitation is a draft — see CloseExpiredCouponParticipationInvitations
 * for how the linked coupon is created/activated on fulfillment.
 */
class CouponParticipationInvitation extends Model
{
    use HasUuids;

    protected $fillable = [
        'coupon_id',
        'title',
        'description',
        'max_participants',
        'min_fee_amount',
        'currency',
        'registration_deadline',
        'status',
        'created_by_admin_id',
    ];

    protected $casts = [
        'max_participants' => 'integer',
        'min_fee_amount' => 'integer',
        'registration_deadline' => 'datetime',
    ];

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_FULFILLED = 'fulfilled';

    public const STATUS_CANCELLED = 'cancelled';

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(CouponParticipationRequest::class, 'invitation_id');
    }

    public function approvedRequests(): HasMany
    {
        return $this->requests()->whereIn('status', ['approved', 'paid']);
    }

    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function isFull(): bool
    {
        return $this->approvedRequests()->count() >= $this->max_participants;
    }

    public function isExpired(): bool
    {
        return $this->registration_deadline !== null && $this->registration_deadline->isPast();
    }
}
