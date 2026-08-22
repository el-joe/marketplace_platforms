<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    use HasUuids, HasFactory;
    protected $fillable = [
        'code',
        'name',
        'description',
        'bank_name',
        'title_ar',
        'title_en',
        'terms_ar',
        'terms_en',
        'type',
        'value',
        'currency',
        'scope',
        'vendor_id',
        'category_id',
        'min_order_amount',
        'max_discount',
        'usage_limit_total',
        'usage_limit_per_customer',
        'max_orders_per_customer_per_month',
        'times_used',
        'customer_eligibility',
        'eligible_customer_ids',
        'country_ids',
        'funded_by',
        'vendor_share_pct',
        'valid_from',
        'valid_until',
        'is_active',
        'is_stackable',
        'created_by_user_id',
    ];

    protected $casts = [
        'type' => \App\Enums\CouponType::class,
        'scope' => \App\Enums\CouponScope::class,
        'customer_eligibility' => \App\Enums\CouponCustomerEligibility::class,
        'terms_ar' => 'array',
        'terms_en' => 'array',
        'country_ids' => 'array',
        'eligible_customer_ids' => 'array',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'is_active' => 'boolean',
        'is_stackable' => 'boolean',
        'min_order_amount' => 'integer',
        'max_discount' => 'integer',
        'value' => 'decimal:2',
    ];

    public function isBankOffer(): bool
    {
        return filled($this->bank_name);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_user_id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'coupon_products');
    }

    public function couponProducts(): HasMany
    {
        return $this->hasMany(CouponProduct::class);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', 1)
            ->where('valid_from', '<=', now())
            ->where('valid_until', '>=', now());
    }

    public function scopeForCountry($query, string $countryId)
    {
        return $query->whereNull('country_ids')
            ->orWhereJsonContains('country_ids', $countryId);
    }
}
