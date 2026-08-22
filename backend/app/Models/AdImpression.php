<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdImpression extends Model
{
    use HasUuids;

    protected $fillable = [
        'ad_campaign_id',
        'vendor_listing_id',
        'customer_id',
        'session_id',
        'placement_code',
        'search_query',
        'position_shown',
        'bid_at_impression',
        'quality_score_at_impression',
        'was_clicked',
        'was_converted',
        'cost_charged',
        'country_id',
        'device_type',
        'shown_at',
        'clicked_at',
        'converted_at',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdCampaign::class, 'ad_campaign_id');
    }

    public function vendorListing(): BelongsTo
    {
        return $this->belongsTo(VendorListing::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(AdClick::class, 'ad_impression_id');
    }
}
