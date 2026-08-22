<?php

namespace App\Models;

use App\Contracts\TravelAgencyAuthUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class TravelAgencyMember extends Authenticatable implements TravelAgencyAuthUser
{
    use HasUuids, SoftDeletes, Notifiable, HasRoles;

    protected string $guard_name = 'travel_agency';

    protected $fillable = [
        'travel_agency_id',
        'name',
        'email',
        'phone',
        'password',
        'role',
        'is_owner',
        'is_active',
        'email_verified_at',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'is_owner' => 'boolean',
            'password' => 'hashed',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────────

    public function travelAgency(): BelongsTo
    {
        return $this->belongsTo(TravelAgency::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isOwner(): bool
    {
        return $this->is_owner;
    }

    public function getMemberType(): string
    {
        return $this->isOwner() ? 'owner' : 'member';
    }
}
