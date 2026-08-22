<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassifiedInquiry extends Model
{
    use HasUuids;

    protected $fillable = [
        'classified_listing_id', 'customer_id',
        'message', 'contact_phone', 'status',
    ];

    protected $casts = [
        'status' => \App\Enums\ClassifiedInquiryStatus::class,
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(ClassifiedListing::class, 'classified_listing_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
