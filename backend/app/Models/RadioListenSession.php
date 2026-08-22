<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RadioListenSession extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'radio_channel_id',
        'customer_id',
        'session_id',
        'started_at',
        'ended_at',
        'duration_seconds',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at'   => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(RadioChannel::class, 'radio_channel_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
