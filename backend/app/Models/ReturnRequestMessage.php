<?php

namespace App\Models;

use App\Enums\DisputeMessageSenderRole;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReturnRequestMessage extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'return_request_id',
        'sender_user_id',
        'sender_role',
        'message',
        'is_internal_note',
        'created_at',
    ];

    protected $casts = [
        'is_internal_note' => 'boolean',
        'created_at'       => 'datetime',
        'sender_role'      => DisputeMessageSenderRole::class,
    ];

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ReturnRequestMessageAttachment::class);
    }

    public function scopeVisibleToVendor($query): void
    {
        $query->where('is_internal_note', false);
    }
}
