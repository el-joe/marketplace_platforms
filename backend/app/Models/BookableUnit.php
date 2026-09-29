<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class BookableUnit extends Model
{
    use HasUuids;

    protected $fillable = [
        'travel_agency_id',
        'travel_package_id',
        'name',
        'name_ar',
        'type',
        'capacity',
        'description',
        'status',
        'approved_by_admin_id',
        'approved_at',
        'rejected_by_admin_id',
        'rejected_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function agency(): BelongsTo
    {
        return $this->belongsTo(TravelAgency::class, 'travel_agency_id');
    }

    public function travelPackage(): BelongsTo
    {
        return $this->belongsTo(TravelPackage::class, 'travel_package_id');
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

    public function photos(): HasMany
    {
        return $this->hasMany(BookableUnitPhoto::class)->orderBy('position');
    }

    public function getPrimaryPhotoUrlAttribute(): ?string
    {
        $photo = $this->photos->firstWhere('is_primary', true) ?? $this->photos->first();

        if ($photo === null) {
            return null;
        }

        return Storage::url($photo->file_path);
    }
}
