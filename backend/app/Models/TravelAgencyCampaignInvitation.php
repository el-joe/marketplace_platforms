<?php

namespace App\Models;

use App\Enums\VendorCampaignInvitationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TravelAgencyCampaignInvitation extends Model
{
    use HasUuids;

    protected $fillable = [
        'travel_agency_campaign_offer_id',
        'marketer_id',
        'status',
        'marketer_note',
        'vendor_note',
        'responded_at',
        'expires_at',
        'resulting_campaign_id',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
        'expires_at' => 'datetime',
        'status' => VendorCampaignInvitationStatus::class,
    ];

    public function offer(): BelongsTo
    {
        return $this->belongsTo(TravelAgencyCampaignOffer::class, 'travel_agency_campaign_offer_id');
    }

    public function marketer(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'id')->whereNotNull('marketer_type');
    }

    public function resultingCampaign(): BelongsTo
    {
        return $this->belongsTo(MarketerCampaign::class, 'resulting_campaign_id');
    }

    public function isExpired(): bool
    {
        return $this->status === VendorCampaignInvitationStatus::Pending
            && $this->expires_at !== null
            && $this->expires_at->isPast();
    }
}
