<?php

namespace App\Models;

use App\Casts\DecimalMoney;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MarketerCampaignInvitation extends Model
{
    use HasUuids;

    protected $fillable = [
        'campaign_id', 'marketer_id', 'status',
        'acceptance_window_hours', 'expires_at', 'responded_at',
        'marketer_note', 'decline_reason',
        'referral_code', 'referral_link', 'qr_code_path',
        'whatsapp_sent', 'whatsapp_sent_at', 'whatsapp_attempts', 'whatsapp_last_error', 'whatsapp_failed_at',
        'replaced_invitation_id', 'replaced_at',
        'whatsapp_resent_at', 'whatsapp_resend_count',
        'total_commission_earned', 'total_conversions',
        'platform_fee_amount', 'platform_fee_currency',
        'platform_fee_vat_rate', 'platform_fee_vat_amount', 'platform_fee_paid_via', 'platform_fee_paid_at',
        'platform_fee_status', 'platform_fee_payer', 'platform_fee_recorded_at',
        'platform_fee_marketing_amount', 'marketer_fee_share_pct', 'marketer_payout_amount',
        'marketer_credited_at', 'marketer_payout_wallet_tx_id',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'responded_at' => 'datetime',
        'whatsapp_sent' => 'boolean',
        'whatsapp_sent_at' => 'datetime',
        'whatsapp_failed_at' => 'datetime',
        'whatsapp_resent_at' => 'datetime',
        'replaced_at' => 'datetime',
        'platform_fee_recorded_at' => 'datetime',
        'platform_fee_paid_at' => 'datetime',
        'platform_fee_vat_rate' => 'decimal:2',
        'platform_fee_amount' => DecimalMoney::class,
        'platform_fee_vat_amount' => DecimalMoney::class,
        'total_commission_earned' => DecimalMoney::class,
        'platform_fee_marketing_amount' => DecimalMoney::class,
        'marketer_fee_share_pct' => 'decimal:4',
        'marketer_payout_amount' => DecimalMoney::class,
        'marketer_credited_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MarketerCampaign::class);
    }

    public function marketer(): BelongsTo
    {
        return $this->belongsTo(Marketer::class, 'marketer_id');
    }

    public function replacedInvitation(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaced_invitation_id');
    }

    public function marketerListing(): HasOne
    {
        return $this->hasOne(MarketerListing::class, 'invitation_id');
    }

    public function conversions(): HasMany
    {
        return $this->hasMany(MarketerCampaignConversion::class, 'invitation_id');
    }

    public function samples(): HasMany
    {
        return $this->hasMany(MarketerCampaignSample::class, 'invitation_id');
    }

    public function payoutItems(): HasMany
    {
        return $this->hasMany(PayoutItem::class, 'invitation_id');
    }

    /** Fee + VAT + this invitation's marketing-fee share: what the payer is actually charged. */
    public function platformFeeTotal(): float
    {
        return round(
            (float) $this->platform_fee_amount
            + (float) $this->platform_fee_vat_amount
            + (float) $this->platform_fee_marketing_amount,
            2
        );
    }

    public function whatsappFailed(): bool
    {
        return ! $this->whatsapp_sent && $this->whatsapp_failed_at !== null;
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    public function isTimedOut(): bool
    {
        return $this->status === 'timed_out' || ($this->isPending() && $this->expires_at?->isPast());
    }

    public function isInfluencerType(): bool
    {
        return $this->marketer?->isInfluencer();
    }

    public function hasPendingFee(): bool
    {
        return $this->platform_fee_status === 'pending';
    }
}
