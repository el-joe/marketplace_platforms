<?php

namespace App\Models;

use App\Enums\ProductStatus;
use App\Enums\VendorListingStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'category_id',
        'brand_id',
        'name_en',
        'name_ar',
        'slug',
        'model_number',
        'gtin',
        'description_en',
        'description_ar',
        'short_desc_en',
        'short_desc_ar',
        'status',
        'is_featured',
        'requires_brand_auth',
        'is_age_restricted',
        'min_age',
        'is_hazardous',
        'has_variants',
        'ai_quality_score',
        'seller_count',
        'total_sold',
        'view_count',
        'seo_title_en',
        'seo_title_ar',
        'seo_description_en',
        'seo_description_ar',
        'published_at',
        'created_by_admin_id',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'requires_brand_auth' => 'boolean',
        'is_age_restricted' => 'boolean',
        'is_hazardous' => 'boolean',
        'has_variants' => 'boolean',
        'min_age' => 'integer',
        'seller_count' => 'integer',
        'total_sold' => 'integer',
        'view_count' => 'integer',
        'ai_quality_score' => 'integer',
        'published_at' => 'datetime',
        'status' => ProductStatus::class,
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('position');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('position');
    }

    public function primaryImage(): HasMany
    {
        return $this->hasMany(ProductImage::class)
            ->where('is_primary', true)
            ->orderBy('position');
    }

    public function countrySettings(): HasMany
    {
        return $this->hasMany(ProductCountrySetting::class);
    }

    public function highlights(): HasMany
    {
        return $this->hasMany(ProductHighlight::class)->orderBy('position');
    }

    public function specifications(): HasMany
    {
        return $this->hasMany(ProductSpecification::class)->orderBy('position');
    }

    public function coupons(): BelongsToMany
    {
        return $this->belongsToMany(Coupon::class, 'coupon_products');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function frequentlyBoughtTogether(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'frequently_bought_together_items',
            'product_id',
            'related_product_id'
        )->withPivot('position')->orderBy('frequently_bought_together_items.position');
    }

    /**
     * Order products by their vendor listings' rating, since rating is no longer
     * stored on products. Used by list/carousel queries that sort products directly
     * (not listing-first queries, which already sort on vendor_listings.rating_avg).
     */
    public function scopeOrderByRating($query, string $direction = 'desc')
    {
        return $query->orderByRaw(
            "(SELECT COALESCE(SUM(vl.rating_avg * vl.rating_count) / NULLIF(SUM(vl.rating_count), 0), 0)
              FROM vendor_listings vl
              JOIN product_variants pv ON pv.id = vl.product_variant_id
              WHERE pv.product_id = products.id AND vl.status = '" . VendorListingStatus::Active->value . "' AND vl.deleted_at IS NULL
             ) " . $direction
        );
    }

    /**
     * Rating is tracked per listing (vendor_listings / admin_listings), not on
     * products. This aggregates across this product's active listings on read, weighted
     * by each listing's own rating_count.
     */
    public function ratingSummary(): array
    {
        $vendorAgg = DB::table('vendor_listings as vl')
            ->join('product_variants as pv', 'pv.id', '=', 'vl.product_variant_id')
            ->where('pv.product_id', $this->id)
            ->where('vl.status', VendorListingStatus::Active->value)
            ->whereNull('vl.deleted_at')
            ->selectRaw('COALESCE(SUM(vl.rating_avg * vl.rating_count), 0) as weighted, COALESCE(SUM(vl.rating_count), 0) as count')
            ->first();

        $adminAgg = DB::table('admin_listings as apl')
            ->join('product_variants as pv', 'pv.id', '=', 'apl.product_variant_id')
            ->where('pv.product_id', $this->id)
            ->where('apl.status', \App\Enums\AdminListingStatus::Active->value)
            ->selectRaw('COALESCE(SUM(apl.rating_avg * apl.rating_count), 0) as weighted, COALESCE(SUM(apl.rating_count), 0) as count')
            ->first();

        $count = (int) $vendorAgg->count + (int) $adminAgg->count;
        $weighted = (float) $vendorAgg->weighted + (float) $adminAgg->weighted;

        return [
            'rating_avg' => $count > 0 ? round($weighted / $count, 2) : 0.0,
            'rating_count' => $count,
        ];
    }
}
