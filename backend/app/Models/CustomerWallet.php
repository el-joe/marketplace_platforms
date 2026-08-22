<?php

namespace App\Models;

use App\Exceptions\InsufficientWalletBalanceException;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class CustomerWallet extends Model
{
    use HasUuids;

    protected $table = 'customer_wallets';

    protected $fillable = [
        'customer_id',
        'balance',
        'currency_code',
    ];

    protected $casts = [
        'balance' => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class, 'customer_id', 'customer_id');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(VoucherRedemption::class, 'customer_wallet_id');
    }

    public function credit(int $amount): void
    {
        DB::table('customer_wallets')->where('id', $this->id)->increment('balance', $amount);
        $this->refresh();
    }

    public function debit(int $amount): void
    {
        if ($this->balance < $amount) {
            throw new InsufficientWalletBalanceException();
        }

        DB::table('customer_wallets')->where('id', $this->id)->decrement('balance', $amount);
        $this->refresh();
    }
}
