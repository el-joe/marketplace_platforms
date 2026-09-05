<?php

namespace App\Models;

use App\Enums\VendorGlobalStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Marketer extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'phone',
        'marketer_type',
        'whatsapp_for_campaigns',
        'global_status',
        'country_id',
        'approved_at',
        'approved_by_admin_id',
        'rejection_reason',
        'onboarding_completed_at',
        'last_login_at',
        'last_login_ip',
        'total_campaigns',
        'total_conversions',
        'total_earnings',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'       => 'datetime',
            'approved_at'             => 'datetime',
            'onboarding_completed_at' => 'datetime',
            'last_login_at'           => 'datetime',
            'total_earnings'          => 'integer',
            'global_status'           => VendorGlobalStatus::class,
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function marketerAdmins(): HasMany
    {
        return $this->hasMany(MarketerAdmin::class, 'marketer_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by_admin_id');
    }

    public function marketerProfile(): HasOne
    {
        return $this->hasOne(MarketerProfile::class, 'marketer_id');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(MarketerCampaignInvitation::class, 'marketer_id');
    }

    // ── Type helpers ───────────────────────────────────────────────────────

    public function isInfluencer(): bool
    {
        return $this->marketer_type === 'influencer';
    }

    public function isAffiliate(): bool
    {
        return $this->marketer_type === 'affiliate';
    }

    public function isActive(): bool
    {
        return $this->global_status?->value === 'active'
            || (string) $this->global_status === 'active';
    }

    public function isPending(): bool
    {
        return (string) $this->global_status === 'pending';
    }
}
