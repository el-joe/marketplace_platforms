<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Enums\DeliveryAgentStatus;
use App\Enums\DeliveryAgentType;
use App\Enums\DeliveryAgentVehicleType;
use App\Models\DeliveryZone;
use App\Models\DeliveryAgentEarning;
use App\Models\DeliveryAgentPayout;
use App\Models\DeliveryAgentDocument;
use App\Models\DeliveryAgentShift;
use Tymon\JWTAuth\Contracts\JWTSubject;

class DeliveryAgent extends Authenticatable implements JWTSubject
{
    use HasUuids, SoftDeletes, Notifiable, HasApiTokens;

    protected string $guard = 'delivery';

    protected $fillable = [
        'country_id',
        'shipping_company_id',
        'added_by_supervisor_id',
        'zone_id',
        'name',
        'email',
        'phone',
        'password',
        'status',
        'agent_type',
        'vehicle_type',
        'national_id',
        'vehicle_plate',
        'emergency_contact_name',
        'emergency_contact_phone',
        'base_salary',
        'per_delivery_fee',
        'current_latitude',
        'current_longitude',
        'last_location_at',
        'is_available',
        'rating_avg',
        'total_deliveries',
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
            'password' => 'hashed',
            'is_available' => 'boolean',
            'last_location_at' => 'datetime',
            'last_login_at' => 'datetime',
            'current_latitude' => 'float',
            'current_longitude' => 'float',
            'rating_avg' => 'float',
            'agent_type' => DeliveryAgentType::class,
            'vehicle_type' => DeliveryAgentVehicleType::class,
            'status' => DeliveryAgentStatus::class,
        ];
    }

    // ── JWT ───────────────────────────────────────────────────────────────

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return ['guard' => 'delivery_api'];
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function shippingCompany(): BelongsTo
    {
        return $this->belongsTo(ShippingCompany::class, 'shipping_company_id');
    }

    public function addedBySupervisor(): BelongsTo
    {
        return $this->belongsTo(ShippingCompanySupervisor::class, 'added_by_supervisor_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class, 'zone_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DeliveryAssignment::class, 'agent_id');
    }

    public function earnings(): HasMany
    {
        return $this->hasMany(DeliveryAgentEarning::class, 'agent_id');
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(DeliveryAgentPayout::class, 'agent_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DeliveryAgentDocument::class, 'agent_id');
    }

    public function codSettlements(): HasMany
    {
        return $this->hasMany(DeliveryAgentCodSettlement::class, 'agent_id');
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(DeliveryAgentShift::class, 'agent_id');
    }

    public function locationHistory(): HasMany
    {
        return $this->hasMany(AgentLocationHistory::class, 'agent_id');
    }

    public function deviceTokens(): MorphMany
    {
        return $this->morphMany(DeviceToken::class, 'tokenable');
    }

    public function notifications(): MorphMany
    {
        return $this->morphMany(Notification::class, 'notifiable');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', DeliveryAgentStatus::Active);
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_available', true)->where('status', DeliveryAgentStatus::Active);
    }

    public function scopeOnShift($query)
    {
        return $query->where('status', DeliveryAgentStatus::OnShift);
    }
}
