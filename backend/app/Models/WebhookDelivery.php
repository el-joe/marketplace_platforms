<?php

namespace App\Models;

use App\Enums\WebhookDeliveryStatus;
use Illuminate\Database\Eloquent\Model;

class WebhookDelivery extends Model
{
    protected $fillable = [
        'event_type',
        'payload',
        'received_from',
        'signature',
        'status',
        'attempts',
        'last_error',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempts' => 'integer',
            'processed_at' => 'datetime',
            'status' => WebhookDeliveryStatus::class,
        ];
    }
}
