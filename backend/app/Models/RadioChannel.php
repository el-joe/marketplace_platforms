<?php

namespace App\Models;

use App\Enums\RadioChannelType;
use App\Services\RadioSchedulingService;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RadioChannel extends Model
{
    use HasUuids;

    protected $fillable = [
        'name_en',
        'name_ar',
        'type',
        'stream_url',
        'fallback_media_path',
        'thumbnail_path',
        'country_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'type' => RadioChannelType::class,
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function scheduleSlots(): HasMany
    {
        return $this->hasMany(RadioScheduleSlot::class);
    }

    public function listenSessions(): HasMany
    {
        return $this->hasMany(RadioListenSession::class);
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    public function isCurrentlyScheduled(): bool
    {
        return app(RadioSchedulingService::class)->getActiveSlot($this) !== null;
    }

    public function getCurrentStreamUrlAttribute(): ?string
    {
        if ($this->isCurrentlyScheduled() && $this->stream_url) {
            return $this->stream_url;
        }

        return $this->fallback_media_path;
    }
}
