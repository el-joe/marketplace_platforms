<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Review extends Model
{
    use HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => ReviewStatus::class,
        ];
    }

    protected $fillable = [
        'product_id',
        'vendor_listing_id',
        'admin_listing_id',
        'customer_id',
        'order_item_id',
        'country_id',
        'rating',
        'title',
        'body',
        'is_verified_purchase',
        'status',
        'ai_flag_reason',
        'moderated_by_admin_id',
        'helpful_count',
        'not_helpful_count',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function vendorListing(): BelongsTo
    {
        return $this->belongsTo(VendorListing::class);
    }

    public function adminListing(): BelongsTo
    {
        return $this->belongsTo(AdminListing::class, 'admin_listing_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function moderatedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'moderated_by_admin_id');
    }

    public function vendorReply(): HasOne
    {
        return $this->hasOne(ReviewVendorReply::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(ReviewVote::class);
    }

    public function files(): MorphMany
    {
        return $this->morphMany(File::class, 'model');
    }

    public function listing(): VendorListing|AdminListing|null
    {
        return $this->admin_listing_id
            ? $this->adminListing
            : $this->vendorListing;
    }

    public function listingType(): string
    {
        return $this->admin_listing_id ? 'admin' : 'vendor';
    }
}
