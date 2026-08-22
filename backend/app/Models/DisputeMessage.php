<?php

namespace App\Models;

use App\Enums\DisputeMessageSenderRole;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class DisputeMessage extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $casts = [
        'created_at' => 'datetime',
        'is_internal_note' => 'boolean',
        'sender_role' => DisputeMessageSenderRole::class,
    ];

    protected $fillable = [
        'dispute_id',
        'sender_user_id',
        'sender_role',
        'message',
        'is_internal_note',
        'created_at',
    ];

    public function dispute(): BelongsTo
    {
        return $this->belongsTo(Dispute::class);
    }

    public function senderUser(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'sender_user_id');
    }

    public function files(): MorphMany
    {
        return $this->morphMany(File::class, 'model');
    }
}
