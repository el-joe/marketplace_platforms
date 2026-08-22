<?php

namespace App\Models;

use App\Enums\TravelBookingStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TravelBooking extends Model
{
    use HasUuids;

    protected $fillable = [
        'booking_number',
        'travel_package_id',
        'customer_id',
        'travelers_count',
        'total_price',
        'passport_file_path',
        'contract_signed_at',
        'contract_signature_data',
        'status',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'contract_signed_at' => 'datetime',
            'status' => TravelBookingStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (TravelBooking $booking) {
            if (empty($booking->booking_number)) {
                $booking->booking_number = 'TRV-' . strtoupper(Str::random(8));
            }
        });
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function package(): BelongsTo
    {
        return $this->belongsTo(TravelPackage::class, 'travel_package_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function totalFormatted(): string
    {
        $currency = $this->package?->currency ?? '';

        return \App\Helpers\CurrencyFormatter::formatPrice($this->total_price, $currency);
    }

    public function isContractSigned(): bool
    {
        return $this->contract_signed_at !== null;
    }
}
