<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingUnitDay extends Model
{
    use HasUuids;

    protected $fillable = [
        'travel_booking_id',
        'bookable_unit_id',
        'date',
        'includes_overnight',
        'time_slot_id',
        'price',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'includes_overnight' => 'boolean',
            'price' => 'integer',
        ];
    }

    public function travelBooking(): BelongsTo
    {
        return $this->belongsTo(TravelBooking::class);
    }

    public function bookableUnit(): BelongsTo
    {
        return $this->belongsTo(BookableUnit::class);
    }

    public function timeSlot(): BelongsTo
    {
        return $this->belongsTo(BookableUnitTimeSlot::class, 'time_slot_id');
    }
}
