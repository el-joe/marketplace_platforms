<?php

namespace App\Models;

use App\Enums\BookableUnitReservationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookableUnitReservation extends Model
{
    use HasUuids;

    protected $fillable = [
        'bookable_unit_id',
        'customer_id',
        'date_from',
        'date_to',
        'time_slot_id',
        'includes_overnight',
        'total_price',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'date_from' => 'date',
            'date_to' => 'date',
            'includes_overnight' => 'boolean',
            'total_price' => 'integer',
            'status' => BookableUnitReservationStatus::class,
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function bookableUnit(): BelongsTo
    {
        return $this->belongsTo(BookableUnit::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function timeSlot(): BelongsTo
    {
        return $this->belongsTo(BookableUnitTimeSlot::class, 'time_slot_id');
    }
}
