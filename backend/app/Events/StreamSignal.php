<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class StreamSignal implements ShouldBroadcastNow
{
    public function __construct(
        public readonly string  $streamKey,
        public readonly string  $type,
        public readonly array   $payload,
        public readonly ?string $targetPeerId,
        public readonly string  $fromPeerId,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel("stream.{$this->streamKey}");
    }

    public function broadcastAs(): string
    {
        return 'signal';
    }

    public function broadcastWith(): array
    {
        return [
            'type'    => $this->type,
            'payload' => $this->payload,
            'target'  => $this->targetPeerId,
            'from'    => $this->fromPeerId,
        ];
    }
}
