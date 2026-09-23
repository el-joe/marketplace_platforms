<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A vendor's/marketer's request to participate in a
 * CouponParticipationInvitation (client feature request #3.2).
 *
 * NOTE — payment: this model only tracks `status`/`paid_at`. There is no
 * real payment-gateway/wallet integration yet (needs a further check
 * against the platform's billing system per the plan). `paid` is currently
 * set manually by an admin "mark as paid" action.
 */
class CouponParticipationRequest extends Model
{
    protected $fillable = [
        'invitation_id',
        'participant_type',
        'participant_id',
        'offered_fee_amount',
        'payment_method',
        'bank_transfer_proof_path',
        'status',
        'paid_at',
        'reviewed_by_admin_id',
    ];

    protected $casts = [
        'offered_fee_amount' => 'integer',
        'paid_at' => 'datetime',
    ];

    public const TYPE_VENDOR = 'vendor';

    public const TYPE_MARKETER = 'marketer';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_PAID = 'paid';

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(CouponParticipationInvitation::class, 'invitation_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'participant_id');
    }

    public function marketer(): BelongsTo
    {
        return $this->belongsTo(Marketer::class, 'participant_id');
    }

    /**
     * Resolve the actual participant model regardless of type, for display
     * purposes (admin list, notifications).
     */
    public function participant(): Vendor|Marketer|null
    {
        return match ($this->participant_type) {
            self::TYPE_VENDOR => $this->vendor,
            self::TYPE_MARKETER => $this->marketer,
            default => null,
        };
    }
}
