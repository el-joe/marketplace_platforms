<?php

namespace App\Models;

use App\Enums\ShippingCompanyStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingCompany extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'legal_name',
        'country_id',
        'contact_email',
        'contact_phone',
        'logo_path',
        'served_countries',
        'served_cities',
        'status',
        'can_supervisors_receive_all_notifications',
        'approved_by_admin_id',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'served_countries'                          => 'array',
            'served_cities'                             => 'array',
            'can_supervisors_receive_all_notifications' => 'boolean',
            'approved_at'                               => 'datetime',
            'status'                                     => ShippingCompanyStatus::class,
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by_admin_id');
    }

    public function supervisors(): HasMany
    {
        return $this->hasMany(ShippingCompanySupervisor::class);
    }

    public function agents(): HasMany
    {
        return $this->hasMany(DeliveryAgent::class, 'shipping_company_id');
    }

    public function fallbackRules(): HasMany
    {
        return $this->hasMany(ShippingFallbackRule::class, 'fallback_shipping_company_id');
    }

    public function carriers(): HasMany
    {
        return $this->hasMany(ShippingCarrier::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', ShippingCompanyStatus::Active);
    }
}
