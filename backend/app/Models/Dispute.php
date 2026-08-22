<?php

namespace App\Models;

use App\Enums\DisputeReason;
use App\Enums\DisputeResolution;
use App\Enums\DisputeStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Dispute extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
            'status' => DisputeStatus::class,
            'reason' => DisputeReason::class,
            'resolution' => DisputeResolution::class,
        ];
    }

    protected $fillable = [
        'id',
        'dispute_number',
        'order_id',
        'sub_order_id',
        'customer_id',
        'vendor_id',
        'return_request_id',
        'reason',
        'description',
        'status',
        'resolution',
        'resolution_notes',
        'compensation',
        'assigned_to_admin_id',
        'resolved_at',
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

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class);
    }

    public function assignedToAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'assigned_to_admin_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(DisputeMessage::class);
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(DisputeEvidence::class);
    }

    public function files(): MorphMany
    {
        return $this->morphMany(File::class, 'model');
    }
}
