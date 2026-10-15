<?php

namespace App\Models;

use App\Casts\DecimalMoney;
use App\Enums\MarketingFeeType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketerCampaign extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'vendor_id', 'owner_type', 'owner_id', 'requested_by_marketer_id',
        'vendor_listing_id', 'admin_listing_id',
        'travel_package_id', 'classified_listing_id', 'marketer_listing_id', 'campaign_category',
        'country_id', 'currency', 'commission_type',
        'max_commission_budget', 'commission_budget_spent', 'marketer_commission_amount',
        'requested_marketer_vendor_ids', // Now stores Marketer UUIDs (previously vendor UUIDs with marketer_type)
        'reviewed_by_admin_id', 'reviewed_at', 'rejection_reason', 'status',
        'auto_approve_at', 'auto_approved',
        'platform_sample_qty_snapshot', 'per_marketer_sample_qty_snapshot',
        'title', 'notes',
        'selected_ad_types', 'vendor_ad_notes',
        'fulfillment_type', 'campaign_subtotal', 'marketing_fee_fixed', 'marketing_fee_variable',
        'marketing_fee_total', 'campaign_grand_total',
        'open_to_affiliates', 'influencer_fixed_reserved',
        'ends_at', 'influencer_fixed_snapshot', 'campaign_fee_status', 'campaign_fee_paid_at', 'campaign_fee_paid_via',
        'influencers_settled_at', 'owner_paused_at',
    ];

    /** The only commission_type written by v3: every marketer is paid according to his own type. */
    public const COMMISSION_TYPE = 'per_marketer_type';

    protected $attributes = [
        'campaign_category' => 'product',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'auto_approve_at' => 'datetime',
        'auto_approved' => 'boolean',
        'requested_marketer_vendor_ids' => 'array',
        'campaign_category' => 'string',
        'selected_ad_types' => 'array',
        'max_commission_budget' => DecimalMoney::class,
        'commission_budget_spent' => DecimalMoney::class,
        'marketer_commission_amount' => DecimalMoney::class,
        'campaign_subtotal' => DecimalMoney::class,
        'marketing_fee_fixed' => DecimalMoney::class,
        'marketing_fee_variable' => DecimalMoney::class,
        'marketing_fee_total' => DecimalMoney::class,
        'campaign_grand_total' => DecimalMoney::class,
        'fulfillment_type' => MarketingFeeType::class,
        'open_to_affiliates' => 'boolean',
        'influencer_fixed_reserved' => DecimalMoney::class,
        'ends_at' => 'datetime',
        'influencer_fixed_snapshot' => 'array',
        'campaign_fee_paid_at' => 'datetime',
        'influencers_settled_at' => 'datetime',
        'owner_paused_at' => 'datetime',
    ];

    public const OWNER_TRAVEL_AGENCY = 'travel_agency';

    /** Vertical of the promoted listing (product | classified | travel); alias of campaign_category. */
    public function getListingTypeAttribute(): string
    {
        return (string) $this->campaign_category;
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function requestedByMarketer(): BelongsTo
    {
        return $this->belongsTo(Marketer::class, 'requested_by_marketer_id');
    }

    public function vendorListing(): BelongsTo
    {
        return $this->belongsTo(VendorListing::class);
    }

    public function adminListing(): BelongsTo
    {
        return $this->belongsTo(AdminListing::class, 'admin_listing_id');
    }

    public function travelPackage(): BelongsTo
    {
        return $this->belongsTo(TravelPackage::class, 'travel_package_id');
    }

    public function classifiedListing(): BelongsTo
    {
        return $this->belongsTo(ClassifiedListing::class, 'classified_listing_id');
    }

    /** P13: the influencer-owned listing this (peer) campaign promotes. */
    public function marketerListing(): BelongsTo
    {
        return $this->belongsTo(MarketerListing::class, 'marketer_listing_id')->withTrashed();
    }

    /** P13: the influencer who promotes (peer campaigns store him as requested_by). */
    public function promoter(): BelongsTo
    {
        return $this->belongsTo(Marketer::class, 'requested_by_marketer_id');
    }

    /** P13: the influencer who owns the promoted listing. */
    public function ownerMarketer(): BelongsTo
    {
        return $this->belongsTo(Marketer::class, 'owner_id');
    }

    public function isPeerPromotion(): bool
    {
        return $this->marketer_listing_id !== null;
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

    public function conversions(): HasMany
    {
        return $this->hasMany(MarketerCampaignConversion::class, 'campaign_id');
    }

    public function payoutItems(): HasMany
    {
        return $this->hasMany(PayoutItem::class, 'campaign_id');
    }

    public function samples(): HasMany
    {
        return $this->hasMany(MarketerCampaignSample::class, 'campaign_id');
    }

    public function invitedMarketers(): HasManyThrough
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

    public function getPromotedItem(): VendorListing|AdminListing|MarketerListing|TravelPackage|ClassifiedListing|null
    {
        return match ($this->campaign_category) {
            'travel' => $this->travelPackage,
            'classified' => $this->classifiedListing,
            default => $this->marketer_listing_id
                ? $this->marketerListing
                : ($this->vendor_listing_id ? $this->vendorListing : $this->adminListing),
        };
    }

    public function getPromotedTitle(): string
    {
        return match ($this->campaign_category) {
            'travel' => $this->travelPackage?->title_ar ?? $this->title ?? '—',
            'classified' => $this->classifiedListing?->title_ar ?? $this->title ?? '—',
            default => $this->vendorListing?->productVariant?->product?->name_ar
                         ?? $this->adminListing?->productVariant?->product?->name_ar
                         ?? $this->marketerListing?->productVariant?->product?->name_ar
                         ?? $this->title ?? '—',
        };
    }

    /**
     * Peer promotion (influencer promotes an influencer's listing). In v3 it earns no per-sale commission: the
     * promoter's reward is decided by the owner outside the campaign engine.
     */
    public function hasNoPeerCommission(): bool
    {
        return $this->marketer_listing_id !== null;
    }

    /** Budget claimed by affiliate / broker commissions and the influencers' reserved fixed amounts. */
    public function budgetUsed(): float
    {
        return round((float) $this->commission_budget_spent + (float) $this->influencer_fixed_reserved, 2);
    }

    /** Budget left for affiliate / broker commissions (null = unlimited legacy / peer campaign). */
    public function budgetRemaining(): ?float
    {
        if ((float) $this->max_commission_budget <= 0) {
            return null;
        }

        return max(0.0, round((float) $this->max_commission_budget - $this->budgetUsed(), 2));
    }

    public function isBudgetExhausted(): bool
    {
        $remaining = $this->budgetRemaining();

        return $remaining !== null && $remaining < 0.01;
    }
}
