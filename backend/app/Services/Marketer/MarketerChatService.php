<?php

namespace App\Services\Marketer;

use App\Events\MarketerChatMessageSent;
use App\Models\Marketer;
use App\Models\MarketerConversation;
use App\Models\MarketerConversationMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Single write path for marketer <-> customer chat so that every sender
 * (customer web/app, marketer panel, marketer app) persists, flags unread
 * and broadcasts identically.
 */
class MarketerChatService
{
    /**
     * Find or create the conversation for a (marketer, customer, listing) triple.
     * The table's unique key ignores NULL listings in MySQL, so profile-level
     * chats (no listing) are serialised with a lock to avoid duplicates.
     */
    public function findOrCreate(Marketer $marketer, string $customerId, ?string $listingId = null, ?string $inquiryId = null): MarketerConversation
    {
        $lookup = ['marketer_id' => $marketer->id, 'customer_id' => $customerId, 'classified_listing_id' => $listingId];

        return Cache::lock("marketer-chat:{$marketer->id}:{$customerId}:{$listingId}", 5)->block(5, function () use ($lookup, $inquiryId) {
            return MarketerConversation::where($lookup)->first()
                ?? MarketerConversation::create($lookup + ['classified_inquiry_id' => $inquiryId]);
        });
    }

    public function post(MarketerConversation $conversation, string $senderType, string $senderId, string $body, ?string $socketId = null): MarketerConversationMessage
    {
        $isCustomer = $senderType === 'customer';

        $message = DB::transaction(function () use ($conversation, $senderType, $senderId, $body, $isCustomer) {
            $message = $conversation->messages()->create([
                'sender_type' => $senderType, 'sender_id' => $senderId, 'body' => trim($body),
            ]);
            $conversation->forceFill([
                'last_message_at' => now(),
                'marketer_has_unread' => $isCustomer,
                'customer_has_unread' => ! $isCustomer,
            ])->save();

            return $message;
        });

        // Broadcast failures (Reverb down) must never fail a message that is already stored.
        try {
            broadcast(new MarketerChatMessageSent($conversation, $message))->toOthers();
        } catch (\Throwable $e) {
            report($e);
        }

        return $message;
    }

    /** Mark the other party's messages as read and clear this side's unread flag. */
    public function markRead(MarketerConversation $conversation, string $readerType): int
    {
        $count = $conversation->messages()
            ->where('sender_type', $readerType === 'customer' ? 'marketer' : 'customer')
            ->whereNull('read_at')->update(['read_at' => now()]);

        $flag = $readerType === 'customer' ? 'customer_has_unread' : 'marketer_has_unread';
        if ($conversation->{$flag}) {
            $conversation->forceFill([$flag => false])->saveQuietly();
        }

        return $count;
    }

    public static function presentMessage(MarketerConversationMessage $m): array
    {
        return [
            'id' => $m->id,
            'conversation_id' => $m->conversation_id,
            'sender_type' => $m->sender_type,
            'body' => $m->body,
            'read_at' => $m->read_at?->toIso8601String(),
            'created_at' => $m->created_at?->toIso8601String(),
        ];
    }
}
