<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Activity extends Model
{
    use HasUuids;

    // activity_log has only created_at, never updated
    const UPDATED_AT = null;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $table = 'activity_log';

    protected $fillable = [
        'log_name',
        'description',
        'subject_type',
        'subject_id',
        'causer_type',
        'causer_id',
        'properties',
        'event',
        'batch_uuid',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** The entity that was acted upon. */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** The entity that caused the activity. */
    public function causer(): MorphTo
    {
        return $this->morphTo();
    }
}
