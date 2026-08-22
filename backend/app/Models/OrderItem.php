<?php

namespace App\Models;

use App\Enums\OrderItemFulfillmentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrderItem extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'product_snapshot' => 'array',
            'shipping_method_snapshot' => 'array',
            'return_eligible_until' => 'date',
            'fulfillment_status' => OrderItemFulfillmentStatus::class,
        ];
    }

    protected $fillable = [
        'id',
        'order_id',
        'sub_order_id',
        'product_variant_id',
        'vendor_listing_id',
        'admin_listing_id',
        'product_snapshot',
        'vendor_id',
        'sku',
        'quantity',
        'unit_price',
        'unit_cost_price',
        'line_subtotal',
        'line_discount',
        'line_tax',
        'line_total',
        'commission_rate_pct',
        'commission_fixed',
        'commission_amount',
        'commission_category_id',
        'fulfillment_status',
        'return_eligible_until',
        'warranty_purchase_id',
        'shipping_method_id',
        'shipping_method_snapshot',
    ];

    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function subOrder(): BelongsTo
    {
        return $this->belongsTo(SubOrder::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function vendorListing(): BelongsTo
    {
        return $this->belongsTo(VendorListing::class);
    }

    public function adminListing(): BelongsTo
    {
        return $this->belongsTo(AdminListing::class, 'admin_listing_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function flashSaleOrder(): HasOne
    {
        return $this->hasOne(FlashSaleOrder::class);
    }

    public function returnRequestItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ReturnRequestItem::class);
    }

    public function warrantyPurchase(): BelongsTo
    {
        return $this->belongsTo(WarrantyPurchase::class);
    }
}
