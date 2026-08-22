<?php

namespace App\Models;

use App\Enums\PayoutStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payout extends Model
{
    // bigint AUTO_INCREMENT — no HasUuids

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end'   => 'date',
            'processed_at' => 'datetime',
            'status'       => PayoutStatus::class,
        ];
    }

    protected $fillable = [
        'payout_number',
        'vendor_id',
        'period_start',
        'period_end',
        'gross_sales',
        'commission',
        'gateway_fee_deducted',
        'refunds_deducted',
        'chargebacks_deducted',
        'storage_fees',
        'ad_fees',
        'other_adjustments',
        'net_amount',
        'currency',
        'status',
        'bank_account_id',
        'payout_method',
        'gateway_reference',
        'approved_by_admin_id',
        'processed_at',
        'failed_reason',
        'receipt_url',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(VendorBankAccount::class, 'bank_account_id');
    }

    public function approvedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by_admin_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayoutItem::class);
    }
}
