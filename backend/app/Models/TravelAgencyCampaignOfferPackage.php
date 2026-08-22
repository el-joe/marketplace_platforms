<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TravelAgencyCampaignOfferPackage extends Model
{
    use HasUuids;

    protected $fillable = [
        'travel_agency_campaign_offer_id',
        'travel_package_id',
        'position',
        'commission_override',
    ];

    protected $casts = [
        'position' => 'integer',
        'commission_override' => 'decimal:2',
    ];

    public function offer(): BelongsTo
    {
        return $this->belongsTo(TravelAgencyCampaignOffer::class, 'travel_agency_campaign_offer_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(TravelPackage::class, 'travel_package_id');
    }
}
