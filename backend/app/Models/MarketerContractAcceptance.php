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
        'ip_address',
        'user_agent',
        'accepted_at',
        'order_id',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
    ];

    public function contractVersion(): BelongsTo
    {
        return $this->belongsTo(MarketerContractVersion::class, 'marketer_contract_version_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
