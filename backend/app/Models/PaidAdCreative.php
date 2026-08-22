<?php

namespace App\Models;

use App\Enums\PaidAdCreativeStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class PaidAdCreative extends Model
{
    use HasUuids;

    protected $casts = [
        'status' => PaidAdCreativeStatus::class,
    ];

    protected $fillable = [
        'paid_ad_booking_id',
        'vendor_id',
        'media_id',
        'mobile_media_id',
        'title_en',
        'title_ar',
        'cta_label_en',
        'cta_label_ar',
        'destination_url',
        'destination_type',
        'destination_reference_id',
        'status',
        'rejection_reason',
        'rejection_code',
        'is_current',
        'reviewed_by_admin_id',
        'reviewed_at',
        'approved_at',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(PaidAdBooking::class, 'paid_ad_booking_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function reviewedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by_admin_id');
    }

    public function files(): MorphMany
    {
        return $this->morphMany(File::class, 'model');
    }
}
