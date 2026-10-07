<?php

namespace App\Models;

use App\Enums\FulfillmentModel;
use App\Enums\MarketerListingStatus;
use App\Services\Customer\MarketerProfileCache;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'warehouse_id',
        'invitation_id',
        'source_type',
        'source_listing_id',
        'fulfillment_model',
        'paused_reason',
        'rejection_reason',
        'approved_by_admin_id',
        'approved_at',
        'price',
        'compare_at_price',
        'currency',
        'status',
        'condition',
        'condition_notes',
        'vendor_sku',
        'low_stock_threshold',
        'declared_weight_grams',
        'declared_length_cm',
        'declared_width_cm',
        'declared_height_cm',
        'handling_class',
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
            'status' => MarketerListingStatus::class,
            'fulfillment_model' => FulfillmentModel::class,
            'price' => 'integer',
            'compare_at_price' => 'integer',
            'approved_at' => 'datetime',
            'low_stock_threshold' => 'integer',
            'declared_weight_grams' => 'integer',
            'declared_length_cm' => 'decimal:2',
            'declared_width_cm' => 'decimal:2',
            'declared_height_cm' => 'decimal:2',
            'score' => 'float',
            'score_calculated_at' => 'datetime',
            'rating_avg' => 'float',
            'total_sold' => 'integer',
            'rating_count' => 'integer',
            'listing_category' => 'string',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function marketer(): BelongsTo
    {
        return $this->belongsTo(Marketer::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by_admin_id');
    }

    public function warehouseInventories(): HasMany
    {
        return $this->hasMany(WarehouseInventory::class);
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

    public function sourceVendorListing(): BelongsTo
    {
        return $this->belongsTo(VendorListing::class, 'source_listing_id');
    }

    public function sourceAdminListing(): BelongsTo
    {
        return $this->belongsTo(AdminListing::class, 'source_listing_id');
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
            'travel' => $this->travelPackage?->title_ar ?? '—',
            'classified' => $this->classifiedListing?->title_ar ?? '—',
            default => $this->productVariant?->product?->name_ar ?? '—',
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

    /** Rotating promo badges attached to this listing (incl. inactive). */
    public function promoBadges(): HasMany
    {
        return $this->hasMany(ProductPromoBadge::class)->orderBy('sort_order');
    }
}
