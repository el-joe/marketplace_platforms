<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;

class VendorAdmin extends Authenticatable implements JWTSubject
{
    use HasUuids, SoftDeletes, Notifiable, HasRoles;

    protected string $guard = 'vendor';

    protected string $guard_name = 'vendor';

    protected $fillable = [
        'vendor_id',
        'name',
        'email',
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

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    // ── Role helpers ──────────────────────────────────────────────────────────

    public function isManager(): bool
    {
        return $this->hasRole('vendor_manager');
    }

    public function isStaff(): bool
    {
        return $this->hasRole('vendor_staff');
    }

    public function deviceTokens(): MorphMany
    {
        return $this->morphMany(DeviceToken::class, 'tokenable');
    }

    public function notifications(): MorphMany
    {
        return $this->morphMany(Notification::class, 'notifiable');
    }

    // ── Broadcasting ──────────────────────────────────────────────────────────

    public function receivesBroadcastNotificationsOn(): string
    {
        return 'vendor.' . $this->id;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isOwner(): bool
    {
        return $this->is_owner;
    }

    // ── JWTSubject ─────────────────────────────────────────────────────────────

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'guard'     => 'vendor',
            'vendor_id' => $this->vendor_id,
            'role'      => $this->role,
        ];
    }
}
