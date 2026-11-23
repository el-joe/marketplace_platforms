<?php

namespace App\Models;

use App\Enums\OrderItemFulfillmentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
            'unit_price' => 'decimal:4',
            'unit_cost_price' => 'decimal:4',
            'line_subtotal' => 'decimal:4',
            'line_discount' => 'decimal:4',
            'vendor_coupon_cost' => 'decimal:4',
            'line_tax' => 'decimal:4',
            'line_total' => 'decimal:4',
            'commission_fixed' => 'decimal:4',
            'commission_amount' => 'decimal:4',
            'marketer_commission' => 'decimal:4',
            'platform_commission_after_discount' => 'decimal:4',
            'source_link_clicked_at' => 'datetime',
            'source_link_rule_snapshot' => 'array',
            'source_link_discount' => 'decimal:4',
        ];
    }

    protected $fillable = [
        'id',
        'order_id',
        'sub_order_id',
        'product_variant_id',
        'vendor_listing_id',
        'admin_listing_id',
        'marketer_listing_id',
        'marketer_campaign_invitation_id',
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
        'vendor_coupon_cost',
        'marketer_commission',
        'platform_commission_after_discount',
        'source_link_channel',
        'source_link_clicked_at',
        'source_link_rule_id',
        'source_link_rule_snapshot',
        'source_link_discount',
        'fulfillment_status',
        'return_eligible_until',
        'warranty_purchase_id',
        'shipping_method_id',
        'shipping_method_snapshot',
    ];

    /** WS12 #24: the customer arrived through the vendor's own product link / QR code. */
    public function cameViaVendorProductLink(): bool
    {
        return $this->source_link_channel !== null;
    }

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

    public function marketerListing(): BelongsTo
    {
        return $this->belongsTo(MarketerListing::class, 'marketer_listing_id');
    }

    public function marketerCampaignInvitation(): BelongsTo
    {
        return $this->belongsTo(MarketerCampaignInvitation::class, 'marketer_campaign_invitation_id');
    }

    public function marketerConversion(): HasOne
    {
        return $this->hasOne(MarketerCampaignConversion::class, 'order_item_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function flashSaleOrder(): HasOne
    {
        return $this->hasOne(FlashSaleOrder::class);
    }

    public function returnRequestItems(): HasMany
    {
        return $this->hasMany(ReturnRequestItem::class);
    }

    public function warrantyPurchase(): BelongsTo
    {
        return $this->belongsTo(WarrantyPurchase::class);
    }

    public function customAttributeValues(): HasMany
    {
        return $this->hasMany(OrderItemCustomAttributeValue::class);
    }

    /**
     * enhancement.md P-13: the exact warehouse_inventory row(s) this
     * item's stock was reserved from.
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(OrderItemAllocation::class);
    }
}
