<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookableUnitAvailability extends Model
{
    use HasUuids;

    protected $table = 'bookable_unit_availability';

    protected $fillable = [
        'bookable_unit_id',
        'date',
        'is_available',
        'capacity_override',
        'price_day_only',
        'price_with_overnight',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_available' => 'boolean',
            'capacity_override' => 'integer',
            'price_day_only' => 'integer',
            'price_with_overnight' => 'integer',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function bookableUnit(): BelongsTo
    {
        return $this->belongsTo(BookableUnit::class);
    }
}
