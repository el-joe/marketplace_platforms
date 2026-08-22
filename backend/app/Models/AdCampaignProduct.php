<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdCampaignProduct extends Model
{
    use HasUuids;

    protected $fillable = [
        'ad_campaign_id',
        'product_variant_id',
        'vendor_id',
        'vendor_listing_id',
        'admin_listing_id',
        'is_active',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdCampaign::class, 'ad_campaign_id');
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

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
        return $this->belongsTo(AdminListing::class);
    }

    public function getListingAttribute(): VendorListing|AdminListing|null
    {
        return $this->admin_listing_id
            ? $this->adminListing
            : $this->vendorListing;
    }
}
