<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdClick extends Model
{
    use HasUuids;

    protected $fillable = [
        'ad_impression_id',
        'ad_campaign_id',
        'vendor_listing_id',
        'admin_listing_id',
        'customer_id',
        'session_id',
        'ip_address',
        'user_agent',
        'is_fraud_suspect',
        'fraud_reason',
        'cost',
        'country_id',
        'clicked_at',
    ];

    public function impression(): BelongsTo
    {
        return $this->belongsTo(AdImpression::class, 'ad_impression_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdCampaign::class, 'ad_campaign_id');
    }

    public function vendorListing(): BelongsTo
    {
        return $this->belongsTo(VendorListing::class);
    }

    public function adminListing(): BelongsTo
    {
        return $this->belongsTo(AdminListing::class);
    }

    public function getListingAttribute(): VendorListing|AdminListing|null
    {
        return $this->admin_listing_id
            ? $this->adminListing
            : $this->vendorListing;
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
