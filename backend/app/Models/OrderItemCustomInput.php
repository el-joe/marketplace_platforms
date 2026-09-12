<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItemCustomInput extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $casts = [
        'extra_price' => 'integer',
    ];

    protected $fillable = [
        'id',
        'order_item_id',
        'vendor_listing_custom_field_id',
        'vendor_listing_addon_option_id',
        'input_type',
        'label_en',
        'label_ar',
        'value_text',
        'extra_price',
    ];

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function customField(): BelongsTo
    {
        return $this->belongsTo(VendorListingCustomField::class, 'vendor_listing_custom_field_id');
    }

    public function addonOption(): BelongsTo
    {
        return $this->belongsTo(VendorListingAddonOption::class, 'vendor_listing_addon_option_id');
    }
}
