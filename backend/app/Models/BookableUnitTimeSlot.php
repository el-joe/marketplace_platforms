<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookableUnitTimeSlot extends Model
{
    use HasUuids;

    protected $fillable = [
        'bookable_unit_id',
        'slot_type',
        'starts_at',
        'ends_at',
        'price',
    ];

    protected function casts(): array
    {
        return [
            'price' => \App\Casts\DecimalMoney::class,
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function bookableUnit(): BelongsTo
    {
        return $this->belongsTo(BookableUnit::class);
    }

    public function bookingDays(): HasMany
    {
        return $this->hasMany(BookingUnitDay::class, 'time_slot_id');
    }
}
