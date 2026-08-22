<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoucherRedemption extends Model
{
    use HasUuids;

    protected $table = 'voucher_redemptions';

    public const UPDATED_AT = null;

    protected $fillable = [
        'voucher_id',
        'customer_id',
        'customer_wallet_id',
        'amount',
        'currency_code',
        'wallet_balance_after',
        'redeemed_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'wallet_balance_after' => 'integer',
        'redeemed_at' => 'datetime',
    ];

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function customerWallet(): BelongsTo
    {
        return $this->belongsTo(CustomerWallet::class, 'customer_wallet_id');
    }

    /**
     * @return never
     */
    public function update(array $attributes = [], array $options = []): never
    {
        throw new \RuntimeException('VoucherRedemption is append-only and cannot be updated.');
    }

    /**
     * @return never
     */
    public function delete(): never
    {
        throw new \RuntimeException('VoucherRedemption is append-only and cannot be deleted.');
    }
}
