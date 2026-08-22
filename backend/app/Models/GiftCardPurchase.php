<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GiftCardPurchase extends Model
{
    use HasUuids, HasFactory;

    protected $table = 'gift_card_purchases';

    protected $fillable = [
        'gift_card_id',
        'gift_card_batch_id',
        'order_id',
        'buyer_customer_id',
        'amount_paid',
        'currency_code',
        'is_gift',
        'recipient_email',
        'recipient_name',
        'gift_message',
        'delivery_status',
        'delivered_at',
        'delivery_attempts',
    ];

    protected $casts = [
        'amount_paid' => 'integer',
        'is_gift' => 'boolean',
        'delivered_at' => 'datetime',
        'delivery_attempts' => 'integer',
    ];

    public function giftCard(): BelongsTo
    {
        return $this->belongsTo(GiftCard::class, 'gift_card_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(GiftCardBatch::class, 'gift_card_batch_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'buyer_customer_id');
    }
}
