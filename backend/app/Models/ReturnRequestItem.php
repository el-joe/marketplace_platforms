<?php

namespace App\Models;

use App\Enums\ReturnRequestItemCondition;
use App\Enums\ReturnRequestItemRestockDecision;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnRequestItem extends Model
{
    protected function casts(): array
    {
        return [
            'condition_received' => ReturnRequestItemCondition::class,
            'restock_decision' => ReturnRequestItemRestockDecision::class,
        ];
    }

    protected $fillable = [
        'return_request_id',
        'order_item_id',
        'quantity',
        'condition_received',
        'restock_decision',
    ];

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
