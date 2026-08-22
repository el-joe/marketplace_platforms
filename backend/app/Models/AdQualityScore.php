<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdQualityScore extends Model
{
    protected $fillable = [
        'ad_campaign_id',
        'vendor_listing_id',
        'score',
        'ctr_score',
        'relevance_score',
        'landing_score',
        'vendor_score',
        'calculated_at',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdCampaign::class, 'ad_campaign_id');
    }

    public function vendorListing(): BelongsTo
    {
        return $this->belongsTo(VendorListing::class);
    }
}
