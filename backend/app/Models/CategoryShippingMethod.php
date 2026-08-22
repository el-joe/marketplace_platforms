<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryShippingMethod extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'category_id',
        'shipping_method_id',
        'is_default',
        'is_available_for_express_fbn',
        'is_available_for_merchant_fbp',
        'display_priority',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_available_for_express_fbn' => 'boolean',
        'is_available_for_merchant_fbp' => 'boolean',
        'display_priority' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }
}
