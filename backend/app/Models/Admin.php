<?php

namespace App\Models;

use App\Enums\AdminStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class Admin extends Authenticatable
{
    use HasFactory, HasUuids, Notifiable, HasRoles;

    protected string $guard_name = 'admin';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'country_id',
        'status',
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
            'status' => AdminStatus::class,
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────────

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function loginSessions(): HasMany
    {
        return $this->hasMany(AdminLoginSession::class);
    }

    public function files(): MorphMany
    {
        return $this->morphMany(File::class, 'model');
    }

    public function acquisitionCommissions(): HasMany
    {
        return $this->hasMany(VendorAcquisitionCommission::class, 'admin_id');
    }

    public function notifications(): MorphMany
    {
        return $this->morphMany(Notification::class, 'notifiable');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class, 'causer_id');
    }

    public function marketerInvitations(): HasMany
    {
        return $this->hasMany(AdminMarketerInvitation::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isAdmin(): bool
    {
        return true;
    }

    public function getAvatarUrlAttribute(): string
    {
        $file = $this->files()->where('key', 'avatar')->first();
        return $file ? $file->full_path : 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&color=FFFFFF&background=0284c7';
    }
}
