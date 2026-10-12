<?php

namespace App\Models;

use App\Casts\DecimalMoney;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class MarketerCampaignSample extends Model
{
    use HasUuids;

    protected $fillable = [
        'campaign_id', 'invitation_id', 'sample_owner', 'sample_type', 'quantity',
        'status', 'stock_deducted_at', 'commission_amount', 'commission_currency',
        'dispatched_at', 'delivered_at', 'cancelled_at', 'delivery_address_snapshot',
    ];

    public const TYPE_ADMIN = 'admin';

    public const TYPE_INFLUENCER = 'influencer';

    public const TYPE_AFFILIATE = 'affiliate';

    protected $casts = [
        'commission_amount' => DecimalMoney::class,
        'cancelled_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'stock_deducted_at' => 'datetime',
        'delivered_at' => 'datetime',
        'delivery_address_snapshot' => 'array',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MarketerCampaign::class);
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(MarketerCampaignInvitation::class);
    }

    public function marketer(): HasOneThrough
    {
        return $this->hasOneThrough(
            Marketer::class,
            MarketerCampaignInvitation::class,
            'id',           // PK on invitations
            'id',           // PK on marketers
            'invitation_id', // FK on samples
            'marketer_id'   // FK on invitations
        );
    }

    /** Statement lines this sample has been charged on (at most one). */
    public function payoutItems(): HasMany
    {
        return $this->hasMany(PayoutItem::class, 'campaign_sample_id');
    }

    /** Not cancelled: counts toward the campaign's sample totals. */
    public function scopeLive($query)
    {
        return $query->where('status', '!=', 'cancelled');
    }

    /** Still sitting in stock: neither dispatched nor cancelled. */
    public function scopeUndispatched($query)
    {
        return $query->where('status', 'pending')->whereNull('stock_deducted_at');
    }

    public function customAttributeValues(): HasMany
    {
        return $this->hasMany(MarketerCampaignSampleCustomAttributeValue::class, 'marketer_campaign_sample_id');
    }

    /**
     * Resolve the product this sample is for, through campaign -> listing.
     */
    public function product(): ?Product
    {
        $listing = $this->campaign->vendorListing ?? $this->campaign->adminListing;

        return $listing?->productVariant?->product;
    }
}
