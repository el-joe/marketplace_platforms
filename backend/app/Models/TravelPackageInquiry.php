<?php

namespace App\Models;

use App\Enums\TravelPackageInquiryStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TravelPackageInquiry extends Model
{
    use HasUuids;

    protected $fillable = [
        'travel_package_id',
        'name',
        'phone',
        'email',
        'travelers_count',
        'message',
        'status',
        'close_reason',
        'converted_to_booking_id',
        'contacted_at',
    ];

    protected function casts(): array
    {
        return [
            'contacted_at' => 'datetime',
            'status' => TravelPackageInquiryStatus::class,
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(TravelPackage::class, 'travel_package_id');
    }

    public function convertedBooking(): BelongsTo
    {
        return $this->belongsTo(TravelBooking::class, 'converted_to_booking_id');
    }

    public function statusLabel(): string
    {
        return $this->status->label();
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            TravelPackageInquiryStatus::New       => 'bg-blue-100 text-blue-700',
            TravelPackageInquiryStatus::Contacted => 'bg-amber-100 text-amber-700',
            TravelPackageInquiryStatus::Converted => 'bg-emerald-100 text-emerald-700',
            TravelPackageInquiryStatus::Closed    => 'bg-gray-100 text-gray-500',
        };
    }
}
