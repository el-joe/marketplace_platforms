<?php

namespace App\Models;

use App\Enums\ReturnRequestInspectionResult;
use App\Enums\ReturnRequestLiability;
use App\Enums\ReturnRequestReason;
use App\Enums\ReturnRequestStatus;
use App\Enums\ReturnRequestType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ReturnRequest extends Model
{
    protected function casts(): array
    {
        return [
            'reason' => ReturnRequestReason::class,
            'return_type' => ReturnRequestType::class,
            'status' => ReturnRequestStatus::class,
            'inspection_result' => ReturnRequestInspectionResult::class,
            'liability' => ReturnRequestLiability::class,
        ];
    }

    protected $fillable = [
        'return_number',
        'order_id',
        'sub_order_id',
        'customer_id',
        'vendor_id',
        'reason',
        'reason_description',
        'return_type',
        'status',
        'pickup_address_id',
        'pickup_scheduled_at',
        'received_at_warehouse_at',
        'inspection_result',
        'inspection_notes',
        'liability',
        'refund_amount',
        'refund_id',
        'rejection_reason',
        'reviewed_by_admin_id',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function subOrder(): BelongsTo
    {
        return $this->belongsTo(SubOrder::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function pickupAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'pickup_address_id');
    }

    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }

    public function reviewedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by_admin_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReturnRequestItem::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ReturnRequestMessage::class);
    }

    public function disputes(): HasMany
    {
        return $this->hasMany(Dispute::class);
    }

    public function files(): MorphMany
    {
        return $this->morphMany(File::class, 'model');
    }
}
