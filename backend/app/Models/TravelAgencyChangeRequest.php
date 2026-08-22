<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TravelAgencyChangeRequest extends Model
{
    use HasUuids;

    protected $fillable = [
        'travel_agency_id',
        'requested_by_travel_agency_member_id',
        'section',
        'request_type',
        'current_data',
        'requested_data',
        'agency_note',
        'status',
        'reviewed_by_admin_id',
        'reviewed_at',
        'admin_note',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'current_data' => 'array',
            'requested_data' => 'array',
            'reviewed_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    public function travelAgency(): BelongsTo
    {
        return $this->belongsTo(TravelAgency::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(TravelAgencyMember::class, 'requested_by_travel_agency_member_id');
    }

    public function reviewedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by_admin_id');
    }

    public function scopePending(Builder $q): Builder
    {
        return $q->where('status', 'pending');
    }

    public function scopeForTravelAgency(Builder $q, string $travelAgencyId): Builder
    {
        return $q->where('travel_agency_id', $travelAgencyId);
    }

    public function scopeForSection(Builder $q, string $section): Builder
    {
        return $q->where('section', $section);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}
