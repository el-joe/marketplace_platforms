<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class StreamComment implements ShouldBroadcastNow
{
    public function __construct(
        public readonly string $streamKey,
        public readonly string $commentId,
        public readonly string $author,
        public readonly string $body,
        public readonly string $createdAt,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel("stream.{$this->streamKey}");
    }

    public function broadcastAs(): string
    {
        return 'comment';
    }

    public function broadcastWith(): array
    {
        return [
            'id'         => $this->commentId,
            'author'     => $this->author,
            'body'       => $this->body,
            'created_at' => $this->createdAt,
        ];
    }
}
