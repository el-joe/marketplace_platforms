<?php

namespace App\Models;

use App\Enums\ReviewVendorReplyStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewVendorReply extends Model
{
    protected function casts(): array
    {
        return [
            'status' => ReviewVendorReplyStatus::class,
        ];
    }

    protected $fillable = [
        'review_id',
        'vendor_id',
        'body',
        'status',
    ];

    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
