<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdDailyStat extends Model
{
    protected $fillable = [
        'ad_campaign_id',
        'vendor_listing_id',
        'country_id',
        'date',
        'impressions',
        'clicks',
        'valid_clicks',
        'conversions',
        'spend',
        'revenue_attributed',
        'ctr',
        'cvr',
        'acos',
        'average_position',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdCampaign::class, 'ad_campaign_id');
    }

    public function vendorListing(): BelongsTo
    {
        return $this->belongsTo(VendorListing::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
