<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketerCampaignInvitation extends Model
{
    use HasUuids;

    protected $fillable = [
        'campaign_id', 'marketer_id', 'status',
        'acceptance_window_hours', 'expires_at', 'responded_at',
        'marketer_note', 'decline_reason',
        'referral_code', 'referral_link', 'qr_code_path',
        'whatsapp_sent', 'whatsapp_sent_at',
        'replaced_invitation_id',
        'total_commission_earned', 'total_conversions',
        'platform_fee_amount', 'platform_fee_currency',
        'platform_fee_status', 'platform_fee_recorded_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'responded_at' => 'datetime',
        'whatsapp_sent' => 'boolean',
        'whatsapp_sent_at' => 'datetime',
        'platform_fee_recorded_at' => 'datetime',
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

    public function conversions(): HasMany
    {
        return $this->hasMany(MarketerCampaignConversion::class, 'invitation_id');
    }

    public function samples(): HasMany
    {
        return $this->hasMany(MarketerCampaignSample::class, 'invitation_id');
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
