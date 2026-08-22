<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class ShippingCompanySupervisor extends Authenticatable implements JWTSubject
{
    use HasUuids, SoftDeletes, Notifiable;

    protected $guard = 'shipping_supervisor';

    protected $fillable = [
        'shipping_company_id',
        'country_id',
        'name',
        'email',
        'phone',
        'password',
        'permissions',
        'is_active',
        'receives_all_notifications',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password'                    => 'hashed',
            'permissions'                 => 'array',
            'is_active'                   => 'boolean',
            'receives_all_notifications'  => 'boolean',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function company(): BelongsTo
    {
        return $this->belongsTo(ShippingCompany::class, 'shipping_company_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    // ── JWTSubject ────────────────────────────────────────────────────────────

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'guard'               => 'shipping_supervisor_api',
            'shipping_company_id' => $this->shipping_company_id,
            'country_id'          => $this->country_id,
            'permissions'         => $this->permissions ?? [],
        ];
    }

    public function hasPermission(string $perm): bool
    {
        return \in_array($perm, $this->permissions ?? [], true);
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeReceivingNotifications($query)
    {
        return $query->where('is_active', true)->where('receives_all_notifications', true);
    }
}
