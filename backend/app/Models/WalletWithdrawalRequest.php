<?php

namespace App\Models;

use App\Enums\WalletWithdrawalRequestStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletWithdrawalRequest extends Model
{
    use HasUuids;

    protected $fillable = [
        'wallet_id',
        'amount',
        'currency',
        'bank_name',
        'bank_iban',
        'status',
        'approved_by_admin_id',
        'processed_at',
        'rejection_reason',
    ];

    /** @var int Base currency unit (BIGINT) for money fields renamed in this model */
    protected $casts = [
        'status'       => WalletWithdrawalRequestStatus::class,
        'amount' => 'integer',
        'processed_at' => 'datetime',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function approvedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by_admin_id');
    }

    public function getAmountAttribute(): float
    {
        return $this->amount / 100;
    }
}
