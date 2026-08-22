<?php

namespace App\Models;

use App\Enums\RadioScheduleSlotRecurrence;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RadioScheduleSlot extends Model
{
    use HasUuids;

    protected $fillable = [
        'radio_channel_id',
        'title',
        'starts_at',
        'ends_at',
        'recurrence',
        'recurrence_days',
        'is_active',
        'created_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'starts_at'       => 'datetime',
            'ends_at'         => 'datetime',
            'recurrence_days' => 'array',
            'is_active'       => 'boolean',
            'recurrence'      => RadioScheduleSlotRecurrence::class,
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(RadioChannel::class, 'radio_channel_id');
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }
}
