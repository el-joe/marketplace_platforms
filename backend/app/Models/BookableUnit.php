<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookableUnit extends Model
{
    use HasUuids;

    protected $fillable = [
        'travel_agency_id',
        'name',
        'type',
        'capacity',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function agency(): BelongsTo
    {
        return $this->belongsTo(TravelAgency::class, 'travel_agency_id');
    }

    public function availability(): HasMany
    {
        return $this->hasMany(BookableUnitAvailability::class);
    }

    public function timeSlots(): HasMany
    {
        return $this->hasMany(BookableUnitTimeSlot::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(BookableUnitReservation::class);
    }
}
