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
        'is_current' => 'boolean',
        'version' => 'integer',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    protected $fillable = [
        'paid_ad_booking_id',
        'vendor_id',
        'marketer_id',
        'version',
        'title_en',
        'title_ar',
        'subtitle_en',
        'subtitle_ar',
        'cta_label_en',
        'cta_label_ar',
        'destination_url',
        'destination_type',
        'destination_reference_id',
        'referral_code',
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

    public function marketer(): BelongsTo
    {
        return $this->belongsTo(Marketer::class);
    }

    public function reviewedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by_admin_id');
    }

    public function files(): MorphMany
    {
        return $this->morphMany(File::class, 'model');
    }

    /** @param string $slot One of desktop_en|desktop_ar|mobile_en|mobile_ar */
    public function image(string $slot): ?File
    {
        return $this->files->firstWhere('file_type', "ad_{$slot}");
    }

    public function imagePair(string $device): array
    {
        $en = $this->image("{$device}_en");
        $ar = $this->image("{$device}_ar");

        return [
            'en' => $en?->full_path,
            'ar' => $ar?->full_path ?? $en?->full_path,
        ];
    }
}
