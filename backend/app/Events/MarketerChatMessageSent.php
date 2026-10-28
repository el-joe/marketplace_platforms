<?php

namespace App\Events;

use App\Models\MarketerConversation;
use App\Models\MarketerConversationMessage;
use App\Services\Marketer\MarketerChatService;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class MarketerChatMessageSent implements ShouldBroadcastNow
{
    use InteractsWithSockets;

    public function __construct(
        public readonly MarketerConversation $conversation,
        public readonly MarketerConversationMessage $message,
    ) {}

    /** @return PrivateChannel[] */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("marketer-chat.{$this->conversation->marketer_id}"),
            new PrivateChannel("customer-chat.{$this->conversation->customer_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversation->id,
            'message' => MarketerChatService::presentMessage($this->message),
        ];
    }
}
