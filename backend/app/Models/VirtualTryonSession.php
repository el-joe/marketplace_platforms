<?php

namespace App\Models;

use App\Enums\VirtualTryonSessionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VirtualTryonSession extends Model
{
    use HasUuids;

    protected $fillable = [
        'customer_id',
        'vendor_listing_id',
        'customer_photo_path',
        'result_image_path',
        'status',
        'provider',
        'error_message',
    ];

    protected $casts = [
        'status' => VirtualTryonSessionStatus::class,
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function vendorListing(): BelongsTo
    {
        return $this->belongsTo(VendorListing::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === VirtualTryonSessionStatus::Completed;
    }

    public function isFailed(): bool
    {
        return $this->status === VirtualTryonSessionStatus::Failed;
    }
}
