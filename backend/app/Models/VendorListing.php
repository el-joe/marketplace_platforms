<?php

namespace App\Models;

use App\Enums\GlobalSystemType;
use App\Enums\VendorListingStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendorListing extends Model
{
    use SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'status' => VendorListingStatus::class,
            'global_system_type' => GlobalSystemType::class,
            'vendor_covers_delivery' => 'boolean',
            'declared_weight_grams' => 'integer',
            'declared_length_cm' => 'decimal:2',
            'declared_width_cm' => 'decimal:2',
            'declared_height_cm' => 'decimal:2',
            'campaign_enabled' => 'boolean',
        ];
    }

    protected $fillable = [
        'id',
        'vendor_id',
        'product_variant_id',
        'country_id',
        'warehouse_id',
        'price',
        'compare_at_price',
        'cost_price',
        'currency',
        'condition',
        'condition_notes',
        'fulfillment_model',
        'vendor_sku',
        'vendor_notes',
        'status',
        'rejection_reason',
        'max_order_quantity',
        'low_stock_threshold',
        'buy_box_eligible',
        'buy_box_won_at',
        'total_sold',
        'rating_avg',
        'rating_count',
        'approved_by_admin_id',
        'approved_at',
        'global_system_type',
        'primary_shipping_method_id',
        'score',
        'price_score',
        'fulfillment_score',
        'rating_score',
        'availability_score',
        'calculated_at',
        'next_recalculate_at',
        'vendor_covers_delivery',
        'weight_class',
        'handling_class',
        'declared_weight_grams',
        'declared_length_cm',
        'declared_width_cm',
        'declared_height_cm',
        'campaign_enabled',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function approvedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by_admin_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function warehouseInventories(): HasMany
    {
        return $this->hasMany(WarehouseInventory::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function flashSaleSubmissions(): HasMany
    {
        return $this->hasMany(FlashSaleSubmission::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function marketplaceShippingRule(): HasOne
    {
        return $this->hasOne(MarketplaceShippingRule::class);
    }

    public function primaryShippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class, 'primary_shipping_method_id');
    }

    /**
     * Restricts the query to the single best listing per (product_variant_id, country_id)
     * using the platform-wide "best listing" ordering: active status, score, rating, price.
     */
    public function scopeBestPerVariant(Builder $query, string $countryId): Builder
    {
        return $query->whereRaw('id = (
            SELECT id FROM vendor_listings vl2
            WHERE vl2.product_variant_id = vendor_listings.product_variant_id
              AND vl2.country_id = ?
              AND vl2.status IN (\'active\', \'out_of_stock\')
              AND vl2.deleted_at IS NULL
            ORDER BY
                CASE WHEN vl2.status = \'active\' THEN 0 ELSE 1 END ASC,
                vl2.score IS NULL, vl2.score DESC,
                vl2.rating_avg IS NULL, vl2.rating_avg DESC,
                vl2.rating_count DESC,
                vl2.price ASC
            LIMIT 1
        )', [$countryId]);
    }
}
