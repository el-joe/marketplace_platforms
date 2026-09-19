<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketerContractAcceptance extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'marketer_contract_version_id',
        'customer_id',
        'marketer_id',
        'ip_address',
        'user_agent',
        'accepted_at',
        'order_id',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Audit trail: only the order link may be set (once); rows are never deleted.
        static::updating(function (self $a) {
            $locked = ['marketer_contract_version_id', 'customer_id', 'marketer_id', 'ip_address', 'user_agent', 'accepted_at'];
            if ($a->isDirty($locked) || ($a->isDirty('order_id') && $a->getOriginal('order_id') !== null)) {
                throw new \LogicException('Contract acceptances are immutable.');
            }
        });
        static::deleting(function () {
            throw new \LogicException('Contract acceptances cannot be deleted.');
        });
    }

    public function contractVersion(): BelongsTo
    {
        return $this->belongsTo(MarketerContractVersion::class, 'marketer_contract_version_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function marketer(): BelongsTo
    {
        return $this->belongsTo(Marketer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
