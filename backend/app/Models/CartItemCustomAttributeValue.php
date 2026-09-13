<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItemCustomAttributeValue extends Model
{
    use HasUuids;

    protected $fillable = [
        'cart_item_id',
        'product_custom_attribute_id',
        'value',
    ];

    public function cartItem(): BelongsTo
    {
        return $this->belongsTo(CartItem::class);
    }

    public function productCustomAttribute(): BelongsTo
    {
        return $this->belongsTo(ProductCustomAttribute::class);
    }
}
