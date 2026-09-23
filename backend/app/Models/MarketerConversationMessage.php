<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketerConversationMessage extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = ['conversation_id', 'sender_type', 'sender_id', 'body', 'attachment_path', 'read_at'];

    protected $casts = ['read_at' => 'datetime'];

    public function conversation(): BelongsTo { return $this->belongsTo(MarketerConversation::class, 'conversation_id'); }
}
