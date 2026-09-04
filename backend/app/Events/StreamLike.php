<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class StreamLike implements ShouldBroadcastNow
{
    public function __construct(
        public readonly string $streamKey,
        public readonly int    $likesCount,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel("stream.{$this->streamKey}");
    }

    public function broadcastAs(): string
    {
        return 'like';
    }

    public function broadcastWith(): array
    {
        return ['likes_count' => $this->likesCount];
    }
}
