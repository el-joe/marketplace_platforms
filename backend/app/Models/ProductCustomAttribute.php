<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductCustomAttribute extends Model
{
    use HasUuids;

    protected $fillable = [
        'product_id',
        'label',
        'unit',
        'is_required',
        'sort_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function cartItemValues(): HasMany
    {
        return $this->hasMany(CartItemCustomAttributeValue::class);
    }

    public function orderItemValues(): HasMany
    {
        return $this->hasMany(OrderItemCustomAttributeValue::class);
    }
}
