<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MarketerConversation extends Model
{
    use HasUuids;

    protected $fillable = [
        'marketer_id', 'customer_id', 'classified_listing_id', 'classified_inquiry_id',
        'last_message_at', 'marketer_has_unread', 'customer_has_unread',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'marketer_has_unread' => 'boolean',
        'customer_has_unread' => 'boolean',
    ];

    public function marketer(): BelongsTo { return $this->belongsTo(Marketer::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function messages(): HasMany { return $this->hasMany(MarketerConversationMessage::class, 'conversation_id')->orderBy('created_at'); }
    public function latestMessage(): HasOne { return $this->hasOne(MarketerConversationMessage::class, 'conversation_id')->latestOfMany(); }
    public function classifiedListing(): BelongsTo { return $this->belongsTo(ClassifiedListing::class)->withTrashed(); }
}
