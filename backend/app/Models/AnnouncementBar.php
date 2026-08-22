<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class AnnouncementBar extends Model
{
    use HasUuids;

    protected $casts = [
        'is_dismissible' => 'boolean',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    protected $fillable = [
        'country_id',
        'name',
        'message_en',
        'message_ar',
        'cta_label_en',
        'cta_label_ar',
        'cta_url',
        'bg_color_hex',
        'text_color_hex',
        'is_dismissible',
        'starts_at',
        'ends_at',
        'is_active',
        'priority',
        'created_by_admin_id',
        'updated_by_admin_id',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function updatedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'updated_by_admin_id');
    }

    /**
     * Resolve the single highest-priority announcement bar that is active, within its
     * scheduling window (or has no window set), and targets the given country (or all
     * countries, i.e. country_id IS NULL).
     */
    public static function getActive(string $countryId): ?self
    {
        $now = Carbon::now();

        return static::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->where(fn ($q) => $q->whereNull('country_id')->orWhere('country_id', $countryId))
            ->orderByDesc('priority')
            ->orderByDesc('created_at')
            ->first();
    }
}
