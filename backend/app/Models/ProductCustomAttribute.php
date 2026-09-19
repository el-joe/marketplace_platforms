<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductCustomAttribute extends Model
{
    public const TYPES = ['text', 'number', 'select', 'checkbox', 'notes'];

    /** Fixed body-measurement presets (label EN/AR), all numeric in cm. */
    public const PRESETS = [
        'length' => ['Length', 'الطول'],
        'width' => ['Width', 'العرض'],
        'chest' => ['Chest', 'الصدر'],
        'sleeve' => ['Sleeve', 'الكم'],
        'sleeve_from_neck' => ['Sleeve from neck', 'الكم من الرقبة'],
    ];

    use HasUuids;

    protected $fillable = [
        'product_id',
        'label',
        'type',
        'options',
        'unit',
        'is_required',
        'sort_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'options' => 'array',
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
