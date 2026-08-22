<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

// ARCHITECTURE NOTE: Gift cards are redeemed in the profile/wallet screen (code + PIN).
// On redemption the full remaining_balance is credited to customer_wallets.
// Gift cards do NOT link directly to orders. The customer spends wallet balance
// at checkout via orders.wallet_amount_used.
class GiftCard extends Model
{
    use HasUuids, HasFactory;

    protected $table = 'gift_cards';

    protected $fillable = [
        'gift_card_batch_id',
        'source',
        'code',
        'pin_hash',
        'amount',
        'remaining_balance',
        'currency_code',
        'status',
        'redeemed_by_customer_id',
        'issued_to_customer_id',
        'redeemed_at',
        'expires_at',
        'purchased_by_customer_id',
        'recipient_email',
        'recipient_name',
        'purchase_order_id',
        'delivery_sent_at',
    ];

    protected $hidden = [
        'pin_hash',
    ];

    protected $casts = [
        'amount' => 'integer',
        'remaining_balance' => 'integer',
        'redeemed_at' => 'datetime',
        'expires_at' => 'datetime',
        'delivery_sent_at' => 'datetime',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(GiftCardBatch::class, 'gift_card_batch_id');
    }

    public function redeemedBy(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'redeemed_by_customer_id');
    }

    public function issuedTo(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'issued_to_customer_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(GiftCardTransaction::class, 'gift_card_id');
    }

    public function purchasedBy(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'purchased_by_customer_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'purchase_order_id');
    }

    public function purchase(): HasOne
    {
        return $this->hasOne(GiftCardPurchase::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function scopeForCurrency(Builder $query, string $code): Builder
    {
        return $query->where('currency_code', $code);
    }

    public function getMaskedCodeAttribute(): string
    {
        return substr($this->code, 0, 4).'****'.substr($this->code, -4);
    }

    public static function generateUniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(16));
        } while (static::where('code', $code)->exists());

        return $code;
    }
}
