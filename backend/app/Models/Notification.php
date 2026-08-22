<?php

namespace App\Models;

use App\Enums\NotificationChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Notification extends Model
{
    use HasUuids;

    protected $fillable = [
        'type',
        'notifiable_type',
        'notifiable_id',
        'data',
        'channel',
        'read_at',
        'sent_at',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        // 'type' intentionally left as a plain string: it stores the full
        // notification class name (e.g. App\Notifications\Vendor\...), which
        // is not a closed set of values.
        'channel' => NotificationChannel::class,
        'data' => 'array',
        'read_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    /** The notifiable entity (Admin, Customer, Vendor). */
    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }
}
