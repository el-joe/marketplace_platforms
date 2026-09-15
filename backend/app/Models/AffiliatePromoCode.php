<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliatePromoCode extends Model
{
    use HasUuids;

    protected $fillable = [
        'marketer_id',
        'code',
        'type',
        'value',
        'currency',
        'min_order_amount',
        'max_discount',
        'usage_limit_total',
        'times_used',
        'is_active',
        'valid_from',
        'valid_until',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'min_order_amount' => 'integer',
        'max_discount' => 'integer',
        'usage_limit_total' => 'integer',
        'times_used' => 'integer',
        'is_active' => 'boolean',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
    ];

    public function marketer(): BelongsTo
    {
        return $this->belongsTo(Marketer::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', 1)
            ->where(fn ($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', now()))
            ->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', now()));
    }
}
