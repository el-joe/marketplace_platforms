<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class WalletTransaction extends Model
{
    use HasUuids;

    protected $table = 'wallet_transactions';

    public const UPDATED_AT = null;

    protected $fillable = [
        'wallet_id',
        'customer_id',
        'type',
        'direction',
        'amount',
        'balance_after',
        'currency_code',
        'reference_type',
        'reference_id',
        'source_type',
        'source_id',
        'description',
        'note',
        'performed_by_admin_id',
    ];

    protected $casts = [
        'amount' => 'integer',
        'balance_after' => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::updating(function () {
            throw new RuntimeException('WalletTransaction records are append-only and cannot be updated.');
        });

        static::deleting(function () {
            throw new RuntimeException('WalletTransaction records are append-only and cannot be deleted.');
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function scopeCredits($query)
    {
        return $query->where('direction', 'credit');
    }

    public function scopeDebits($query)
    {
        return $query->where('direction', 'debit');
    }

    public function scopeForCustomer($query, string $customerId)
    {
        return $query->where('customer_id', $customerId);
    }
}
