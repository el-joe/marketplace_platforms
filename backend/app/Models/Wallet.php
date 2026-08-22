<?php

namespace App\Models;

use App\Enums\WalletOwnerType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    use HasUuids;

    protected $fillable = [
        'owner_type',
        'owner_id',
        'balance',
        'pending_balance',
        'currency',
        'is_frozen',
        'frozen_reason',
    ];

    /** @var int Base currency unit (BIGINT) for money fields renamed in this model */
    protected $casts = [
        'owner_type'            => WalletOwnerType::class,
        'balance'         => 'integer',
        'pending_balance' => 'integer',
        'is_frozen'             => 'boolean',
    ];

    public function getOwnerAttribute(): Model|null
    {
        return match ($this->owner_type) {
            WalletOwnerType::Customer      => Customer::find($this->owner_id),
            WalletOwnerType::Vendor        => Vendor::find($this->owner_id),
            WalletOwnerType::DeliveryAgent => DeliveryAgent::find($this->owner_id),
            default                        => null,
        };
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class)->orderByDesc('created_at');
    }

    public function withdrawalRequests(): HasMany
    {
        return $this->hasMany(WalletWithdrawalRequest::class)->orderByDesc('created_at');
    }

    public function getBalanceAttribute(): float
    {
        return $this->attributes['balance'] / 100;
    }

    public function getPendingBalanceAttribute(): float
    {
        return $this->attributes['pending_balance'] / 100;
    }
}
