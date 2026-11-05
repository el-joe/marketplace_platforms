<?php

namespace App\Models;

use App\Models\Concerns\SendsPasswordResetLink;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;

class MarketerAdmin extends Authenticatable implements JWTSubject
{
    use HasRoles, HasUuids, Notifiable, SendsPasswordResetLink, SoftDeletes;

    protected string $guard = 'marketer';

    protected string $guard_name = 'marketer';

    protected $fillable = [
        'marketer_id',
        'name',
        'email',
        'password',
        'is_owner',
        'is_active',
        'email_verified_at',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at'     => 'datetime',
            'is_active'         => 'boolean',
            'is_owner'          => 'boolean',
            'password'          => 'hashed',
        ];
    }

    public function passwordResetBroker(): string
    {
        return 'marketer_admins';
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function marketer(): BelongsTo
    {
        return $this->belongsTo(Marketer::class);
    }

    // ── JWT ───────────────────────────────────────────────────────────────

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'marketer_id' => $this->marketer_id,
            'guard'       => 'marketer',
        ];
    }
}
