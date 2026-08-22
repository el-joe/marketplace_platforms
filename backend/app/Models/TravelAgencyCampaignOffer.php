<?php

namespace App\Models;

use App\Enums\AttributionModel;
use App\Enums\CampaignType;
use App\Enums\CommissionType;
use App\Enums\VendorCampaignOfferStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class TravelAgencyCampaignOffer extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'travel_agency_id',
        'name',
        'description',
        'requirements',
        'campaign_type',
        'offered_commission_rate',
        'commission_type',
        'budget_per_marketer',
        'total_budget',
        'total_spent',
        'starts_at',
        'ends_at',
        'invitation_deadline',
        'status',
        'approved_by_admin_id',
        'approved_at',
        'rejected_by_admin_id',
        'rejection_reason',
        'attribution_model',
        'whatsapp_sharing_enabled',
    ];

    /** @var int Base currency unit (BIGINT) for money fields renamed in this model */
    protected $casts = [
        'offered_commission_rate' => 'decimal:2',
        'budget_per_marketer' => 'integer',
        'total_budget' => 'integer',
        'total_spent' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'invitation_deadline' => 'datetime',
        'approved_at' => 'datetime',
        'whatsapp_sharing_enabled' => 'boolean',
        'campaign_type' => CampaignType::class,
        'commission_type' => CommissionType::class,
        'status' => VendorCampaignOfferStatus::class,
        'attribution_model' => AttributionModel::class,
    ];

    public function agency(): BelongsTo
    {
        return $this->belongsTo(TravelAgency::class, 'travel_agency_id');
    }

    public function approvedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by_admin_id');
    }

    public function rejectedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'rejected_by_admin_id');
    }

    public function packages(): HasMany
    {
        return $this->hasMany(TravelAgencyCampaignOfferPackage::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(TravelAgencyCampaignInvitation::class);
    }

    public function resultingCampaigns(): HasManyThrough
    {
        return $this->hasManyThrough(
            MarketerCampaign::class,
            TravelAgencyCampaignInvitation::class,
            'travel_agency_campaign_offer_id',
            'id',
            'id',
            'resulting_campaign_id'
        );
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', VendorCampaignOfferStatus::Draft->value);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', VendorCampaignOfferStatus::Active->value);
    }

    public function scopeEnded(Builder $query): Builder
    {
        return $query->where('status', VendorCampaignOfferStatus::Ended->value);
    }
}
