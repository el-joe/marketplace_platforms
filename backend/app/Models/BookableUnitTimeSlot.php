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
            'price' => 'integer',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function bookableUnit(): BelongsTo
    {
        return $this->belongsTo(BookableUnit::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(BookableUnitReservation::class, 'time_slot_id');
    }
}
