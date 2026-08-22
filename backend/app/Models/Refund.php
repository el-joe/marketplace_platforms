<?php

namespace App\Models;

use App\Enums\RefundReason;
use App\Enums\RefundStatus;
use App\Enums\RefundType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    use HasUuids;

    protected $fillable = [
        'id',
        'order_id',
        'sub_order_id',
        'original_transaction_id',
        'refund_transaction_id',
        'amount',
        'currency',
        'reason',
        'reason_notes',
        'refund_type',
        'initiated_by_customer_id',
        'approved_by_admin_id',
        'vendor_charged_back',
        'status',
        'gateway_fee_deducted',
        'tax_deducted',
    ];

    protected function casts(): array
    {
        return [
            'status' => RefundStatus::class,
            'reason' => RefundReason::class,
            'refund_type' => RefundType::class,
            'gateway_fee_deducted' => 'integer',
            'tax_deducted' => 'integer',
            'net_refund' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function subOrder(): BelongsTo
    {
        return $this->belongsTo(SubOrder::class);
    }

    public function originalTransaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class, 'original_transaction_id');
    }

    public function refundTransaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class, 'refund_transaction_id');
    }

    public function initiatedByCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'initiated_by_customer_id');
    }

    public function approvedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by_admin_id');
    }
}
