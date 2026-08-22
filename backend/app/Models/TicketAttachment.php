<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class TicketAttachment extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'ticket_message_id',
    ];

    public function ticketMessage(): BelongsTo
    {
        return $this->belongsTo(TicketMessage::class);
    }

    public function files(): MorphMany
    {
        return $this->morphMany(File::class, 'model');
    }
}
