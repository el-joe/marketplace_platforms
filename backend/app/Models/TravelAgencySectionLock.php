<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TravelAgencySectionLock extends Model
{
    use HasUuids;

    public const SECTION_BANK_ACCOUNTS = 'bank_accounts';

    protected $fillable = [
        'travel_agency_id',
        'section',
        'is_locked',
        'locked_reason',
        'locked_by_admin_id',
        'locked_at',
        'unlocked_by_admin_id',
        'unlocked_at',
    ];

    protected function casts(): array
    {
        return [
            'is_locked' => 'boolean',
            'locked_at' => 'datetime',
            'unlocked_at' => 'datetime',
        ];
    }

    public static function sections(): array
    {
        return [
            self::SECTION_BANK_ACCOUNTS,
        ];
    }

    public function travelAgency(): BelongsTo
    {
        return $this->belongsTo(TravelAgency::class);
    }

    public function lockedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'locked_by_admin_id');
    }

    public function unlockedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'unlocked_by_admin_id');
    }
}
