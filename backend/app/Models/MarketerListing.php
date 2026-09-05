<?php

namespace App\Models;

use App\Services\Customer\MarketerProfileCache;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketerListing extends Model
{
    use HasUuids, SoftDeletes;

    /**
     * MarketerListing fulfillment rules:
     *
     * Marketer listings do NOT have their own warehouse inventory.
     * Stock is served from the CAMPAIGN's source listing:
     *   - vendor_listing_id (via invitation.campaign) → VendorListing (FBN/FBM)
     *   - admin_listing_id  (via invitation.campaign) → AdminListing  (Express FBN)
     *
     * When a customer places an order via a marketer referral link:
     * 1. The order_item references the SOURCE listing (not the marketer listing)
     * 2. The MarketerCampaignConversion is created linking order → invitation
     * 3. Inventory is deducted from the campaign source listing's warehouse_inventories
     * 4. The marketer listing price is shown on the storefront but the CART
     *    resolves to the actual source listing at that price
     *
     * This means marketer listings NEVER appear in warehouse_inventories.
     */
    protected $fillable = [
        'marketer_id',
        'product_variant_id',
        'travel_package_id',
        'classified_listing_id',
        'listing_category',
        'country_id',
        'invitation_id',
        'price',
        'compare_at_price',
        'currency',
        'status',
        'condition',
        'score',
        'score_calculated_at',
        'referral_code',
        'referral_link',
        'total_sold',
        'rating_avg',
        'rating_count',
    ];

    protected function casts(): array
    {
        return [
            'price'               => 'integer',
            'compare_at_price'    => 'integer',
            'score'               => 'float',
            'score_calculated_at' => 'datetime',
            'rating_avg'          => 'float',
            'total_sold'          => 'integer',
            'rating_count'        => 'integer',
            'listing_category'    => 'string',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function marketer(): BelongsTo
    {
        return $this->belongsTo(Marketer::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(MarketerCampaignInvitation::class, 'invitation_id');
    }

    public function travelPackage(): BelongsTo
    {
        return $this->belongsTo(TravelPackage::class, 'travel_package_id');
    }

    public function classifiedListing(): BelongsTo
    {
        return $this->belongsTo(ClassifiedListing::class, 'classified_listing_id');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeActive($q)
    {
        return $q->where('status', 'active')->whereNull('deleted_at');
    }

    public function scopeForCountry($q, string $countryId)
    {
        return $q->where('country_id', $countryId);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getDisplayTitle(): string
    {
        return match ($this->listing_category ?? 'product') {
            'travel'     => $this->travelPackage?->title_ar ?? '—',
            'classified' => $this->classifiedListing?->title_ar ?? '—',
            default      => $this->productVariant?->product?->name_ar ?? '—',
        };
    }

    protected static function booted(): void
    {
        $bump = function (self $listing) {
            $slug = MarketerProfile::where('marketer_id', $listing->marketer_id)->value('profile_slug');
            MarketerProfileCache::bump($slug);
        };

        static::saved($bump);
        static::deleted($bump);
    }
}
