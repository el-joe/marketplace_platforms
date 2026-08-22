<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlashSaleOrder extends Model
{
    use HasUuids;

    protected $fillable = [
        'flash_sale_submission_id',
        'flash_sale_id',
        'order_item_id',
        'quantity',
        'flash_price',
        'original_price',
        'discount_amount',
    ];

    protected function casts(): array
    {
        return [
            'flash_price'     => 'integer',
            'original_price'  => 'integer',
            'discount_amount' => 'integer',
        ];
    }

    public function flashSaleSubmission(): BelongsTo
    {
        return $this->belongsTo(FlashSaleSubmission::class);
    }

    public function flashSale(): BelongsTo
    {
        return $this->belongsTo(FlashSale::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
