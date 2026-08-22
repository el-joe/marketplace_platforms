<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Voucher extends Model
{
    use HasUuids, SoftDeletes, HasFactory;

    protected $table = 'vouchers';

    protected $fillable = [
        'code',
        'name',
        'description',
        'title_ar',
        'title_en',
        'amount',
        'currency_code',
        'country_id',
        'customer_eligibility',
        'eligible_customer_ids',
        'usage_limit_total',
        'usage_limit_per_customer',
        'times_redeemed',
        'valid_from',
        'valid_until',
        'is_active',
        'created_by_admin_id',
    ];

    protected $casts = [
        'amount' => 'integer',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'is_active' => 'boolean',
        'eligible_customer_ids' => 'array',
    ];

    public function redemptions(): HasMany
    {
        return $this->hasMany(VoucherRedemption::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', 1)
            ->where('valid_from', '<=', now())
            ->where('valid_until', '>=', now());
    }
}
