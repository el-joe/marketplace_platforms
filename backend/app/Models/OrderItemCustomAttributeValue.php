<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItemCustomAttributeValue extends Model
{
    use HasUuids;

    protected $fillable = [
        'order_item_id',
        'product_custom_attribute_id',
        'label',
        'unit',
        'value',
    ];

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function productCustomAttribute(): BelongsTo
    {
        return $this->belongsTo(ProductCustomAttribute::class);
    }
}
