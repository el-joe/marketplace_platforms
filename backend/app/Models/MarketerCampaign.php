<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketerCampaign extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'vendor_id', 'vendor_listing_id', 'admin_listing_id',
        'country_id', 'currency', 'commission_type',
        'max_commission_budget', 'platform_commission_amount', 'marketer_commission_amount',
        'requested_marketer_vendor_ids', // Now stores Marketer UUIDs (previously vendor UUIDs with marketer_type)
        'reviewed_by_admin_id', 'reviewed_at', 'rejection_reason', 'status',
        'auto_approve_at', 'auto_approved',
        'platform_sample_qty_snapshot', 'per_marketer_sample_qty_snapshot',
        'title', 'notes',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'auto_approve_at' => 'datetime',
        'auto_approved' => 'boolean',
        'requested_marketer_vendor_ids' => 'array',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function vendorListing(): BelongsTo
    {
        return $this->belongsTo(VendorListing::class);
    }

    public function adminListing(): BelongsTo
    {
        return $this->belongsTo(AdminListing::class, 'admin_listing_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by_admin_id');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(MarketerCampaignInvitation::class, 'campaign_id');
    }

    public function tieredRules(): HasMany
    {
        return $this->hasMany(MarketerCampaignTieredRule::class, 'campaign_id')->orderBy('from_sale_number');
    }

    public function conversions(): HasMany
    {
        return $this->hasMany(MarketerCampaignConversion::class, 'campaign_id');
    }

    public function samples(): HasMany
    {
        return $this->hasMany(MarketerCampaignSample::class, 'campaign_id');
    }

    public function invitedMarketers(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(
            Marketer::class,
            MarketerCampaignInvitation::class,
            'campaign_id',    // FK on invitations
            'id',             // PK on marketers
            'id',             // PK on campaigns
            'marketer_id'     // FK on invitations pointing to marketers
        );
    }

    public function scopePendingAdmin($q)
    {
        return $q->where('status', 'pending_admin');
    }

    public function scopeActive($q)
    {
        return $q->where('status', 'active');
    }

    public function scopeForAutoApprove($q)
    {
        return $q->where('status', 'pending_admin')
            ->where('auto_approve_at', '<=', now())
            ->where('auto_approved', false);
    }

    public function isOwnedByAdmin(): bool
    {
        return $this->admin_listing_id !== null;
    }

    public function totalSamplesFor(int $marketerCount): int
    {
        return $this->platform_sample_qty_snapshot + ($marketerCount * $this->per_marketer_sample_qty_snapshot);
    }

    public function getNetPlatformProfitAttribute(): int
    {
        return (int) $this->invitations()
            ->where('platform_fee_status', 'paid')
            ->sum('platform_fee_amount');
    }

    public function getTotalCommissionOwedAttribute(): int
    {
        return (int) $this->conversions()
            ->where('commissioned', false)
            ->sum('commission_amount');
    }
}
