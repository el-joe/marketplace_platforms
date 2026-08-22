<?php

namespace App\Models;

use App\Enums\AdCampaignStatus;
use App\Enums\AdCampaignTargetingType;
use App\Enums\AdCampaignType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AdCampaign extends Model
{
    use HasUuids;

    protected $casts = [
        'starts_at'   => 'datetime',
        'ends_at'     => 'datetime',
        'approved_at' => 'datetime',
        'status' => AdCampaignStatus::class,
        'type' => AdCampaignType::class,
        'targeting_type' => AdCampaignTargetingType::class,
    ];

    protected $fillable = [
        'vendor_id',
        'country_id',
        'name',
        'type',
        'status',
        'budget_total',
        'budget_daily',
        'budget_spent_total',
        'budget_spent_today',
        'bid',
        'targeting_type',
        'starts_at',
        'ends_at',
        'quality_score',
        'approved_by_admin_id',
        'approved_at',
        'rejection_reason',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function approvedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by_admin_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(AdCampaignProduct::class, 'ad_campaign_id');
    }

    public function keywords(): HasMany
    {
        return $this->hasMany(AdCampaignKeyword::class, 'ad_campaign_id');
    }

    public function categoryTargets(): HasMany
    {
        return $this->hasMany(AdCampaignCategoryTarget::class, 'ad_campaign_id');
    }

    public function impressions(): HasMany
    {
        return $this->hasMany(AdImpression::class, 'ad_campaign_id');
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(AdClick::class, 'ad_campaign_id');
    }

    public function dailyStats(): HasMany
    {
        return $this->hasMany(AdDailyStat::class, 'ad_campaign_id');
    }

    public function qualityScore(): HasOne
    {
        return $this->hasOne(AdQualityScore::class, 'ad_campaign_id');
    }

    public function fraudPatterns(): HasMany
    {
        return $this->hasMany(AdFraudPattern::class, 'ad_campaign_id');
    }
}
