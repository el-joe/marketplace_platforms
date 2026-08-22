<?php

namespace App\Models;

use App\Enums\DeliveryAgentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryZone extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'country_id',
        'name',
        'code',
        'city_ids',
        'polygon_coordinates',
        'base_delivery_fee',
        'cod_fee',
        'max_active_agents',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'city_ids' => 'array',
            'polygon_coordinates' => 'array',
            'is_active' => 'boolean',
            'base_delivery_fee' => 'integer',
            'cod_fee' => 'integer',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function agents(): HasMany
    {
        return $this->hasMany(DeliveryAgent::class, 'zone_id');
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(DeliveryAgentShift::class, 'zone_id');
    }

    /**
     * Scope: zones whose city_ids JSON array contains the given city ID.
     */
    public function scopeCoversCity(Builder $query, string $cityId): Builder
    {
        return $query->whereJsonContains('city_ids', $cityId);
    }

    // ── Capacity ──────────────────────────────────────────────────────────

    /**
     * Returns true when the zone has reached its max_active_agents limit.
     * Only counts agents that are currently active or on_shift.
     * If max_active_agents is NULL the zone has no cap and always returns false.
     */
    public function isAtCapacity(): bool
    {
        if ($this->max_active_agents === null) {
            return false;
        }

        return $this->activeAgentCount() >= $this->max_active_agents;
    }

    /**
     * Returns remaining capacity or null if uncapped.
     */
    public function remainingCapacity(): ?int
    {
        if ($this->max_active_agents === null) {
            return null;
        }

        return max(0, $this->max_active_agents - $this->activeAgentCount());
    }

    private function activeAgentCount(): int
    {
        return $this->agents()
            ->whereIn('status', [
                DeliveryAgentStatus::Active->value,
                DeliveryAgentStatus::OnShift->value,
            ])
            ->count();
    }

    // ── Accessors ─────────────────────────────────────────────────────────

    public function citiesResolved(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (empty($this->city_ids)) {
                    return collect();
                }

                return City::whereIn('id', $this->city_ids)
                    ->orderBy('name_en')
                    ->get(['id', 'name_en', 'name_ar']);
            }
        );
    }

}
