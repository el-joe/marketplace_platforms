<?php

namespace App\Models;

use App\Enums\AdminListingStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdminListing extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'warehouse_id',
        'product_variant_id',
        'country_id',
        'price',
        'compare_at_price',
        'cost_price',
        'condition',
        'condition_notes',
        'currency',
        'shipping_cost',
        'primary_shipping_method_id',
        'is_global_shipping',
        'vendor_covers_delivery',
        'status',
        'created_by_admin_id',
        'updated_by_admin_id',
        'max_order_quantity',
        'low_stock_threshold',
        'campaign_enabled',
        'sold_by_label_en',
        'sold_by_label_ar',
        'express_badge_label_en',
        'express_badge_label_ar',
        'search_boost',
        'is_daily_deal',
        'daily_deal_ends_at',
        'total_sold',
        'buy_box_eligible',
        'buy_box_won_at',
        'rating_avg',
        'rating_count',
        'score',
        'price_score',
        'fulfillment_score',
        'rating_score',
        'availability_score',
        'calculated_at',
        'next_recalculate_at',
        'weight_class',
        'handling_class',
        'declared_weight_grams',
        'declared_length_cm',
        'declared_width_cm',
        'declared_height_cm',
        'influencer_commission_percentage',
        'affiliate_commission_percentage',
        'influencer_sample_quota',
        'affiliate_sample_quota',
    ];

    /** @var int Base currency unit (BIGINT) for money fields renamed in this model */
    protected $casts = [
        'price'        => 'integer',
        'compare_at_price' => 'integer',
        'cost_price'   => 'integer',
        'shipping_cost'=> 'integer',
        'is_global_shipping' => 'boolean',
        'vendor_covers_delivery' => 'boolean',
        'campaign_enabled'    => 'boolean',
        'search_boost'        => 'integer',
        'is_daily_deal'       => 'boolean',
        'daily_deal_ends_at'  => 'datetime',
        'rating_avg'          => 'decimal:2',
        'rating_count'        => 'integer',
        'status'              => AdminListingStatus::class,
        'buy_box_eligible'    => 'boolean',
        'buy_box_won_at'      => 'datetime',
        'score'               => 'decimal:4',
        'price_score'         => 'decimal:4',
        'fulfillment_score'   => 'decimal:4',
        'rating_score'        => 'decimal:4',
        'availability_score'  => 'decimal:4',
        'calculated_at'       => 'datetime',
        'next_recalculate_at' => 'datetime',
        'declared_length_cm'  => 'decimal:2',
        'declared_width_cm'   => 'decimal:2',
        'declared_height_cm'  => 'decimal:2',
        'influencer_commission_percentage' => 'decimal:2',
        'affiliate_commission_percentage'  => 'decimal:2',
    ];

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function updatedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'updated_by_admin_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function openMarketEntries(): HasMany
    {
        return $this->hasMany(AdminInfluencerOpenMarketProduct::class);
    }

    public function primaryShippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class, 'primary_shipping_method_id');
    }

    public function warehouseInventories(): HasMany
    {
        return $this->hasMany(WarehouseInventory::class);
    }

    public function warehouseInventory(): HasOne
    {
        return $this->hasOne(WarehouseInventory::class);
    }

    public function marketplaceShippingRule(): HasOne
    {
        return $this->hasOne(MarketplaceShippingRule::class);
    }

    public function flashSaleSubmissions(): HasMany
    {
        return $this->hasMany(FlashSaleSubmission::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function productCostReferences(): HasMany
    {
        return $this->hasMany(ProductCostReference::class);
    }

    public function marketerCampaigns(): HasMany
    {
        return $this->hasMany(MarketerCampaign::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeForCountry(Builder $query, string $countryId): Builder
    {
        return $query->where('country_id', $countryId);
    }

    /**
     * Restricts the query to the single best listing per (product_variant_id, country_id)
     * using the platform-wide "best listing" ordering: score, rating, price.
     */
    public function scopeBestPerVariant(Builder $query, string $countryId): Builder
    {
        return $query->whereRaw('id = (
            SELECT id FROM admin_listings apl2
            WHERE apl2.product_variant_id = admin_listings.product_variant_id
              AND apl2.country_id = ?
              AND apl2.status = \'active\'
              AND apl2.deleted_at IS NULL
            ORDER BY
                apl2.score IS NULL, apl2.score DESC,
                apl2.rating_avg IS NULL, apl2.rating_avg DESC,
                apl2.rating_count DESC,
                apl2.price ASC
            LIMIT 1
        )', [$countryId]);
    }
}
