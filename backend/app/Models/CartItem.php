<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'cart_id',
        'vendor_listing_id',
        'admin_listing_id',
        'selected_shipping_method_id',
        'quantity',
        'unit_price',
        'added_at',
        'warranty_plan_id',
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function warrantyPlan(): BelongsTo
    {
        return $this->belongsTo(WarrantyPlan::class);
    }

    public function vendorListing(): BelongsTo
    {
        return $this->belongsTo(VendorListing::class);
    }

    public function adminListing(): BelongsTo
    {
        return $this->belongsTo(AdminListing::class, 'admin_listing_id');
    }

    public function selectedShippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class, 'selected_shipping_method_id');
    }
}
