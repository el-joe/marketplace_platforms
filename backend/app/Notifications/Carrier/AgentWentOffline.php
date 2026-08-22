<?php

namespace App\Notifications\Carrier;

use App\Models\DeliveryAgent;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;

class AgentWentOffline extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly DeliveryAgent $agent) {}

    public function notificationType(): string
    {
        return 'agent_went_offline';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title'    => 'Agent Offline',
            'message'  => "{$this->agent->name} is now offline",
            'agent_id' => $this->agent->id,
        ];
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'push'];
    }

    public function toPush(object $notifiable): array
    {
        return [
            'title' => 'Agent Offline',
            'body'  => "{$this->agent->name} is now offline",
            'data'  => [
                'screen' => 'agents',
                'id'     => $this->agent->id,
            ],
        ];
    }

    // broadcastOn receives the notifiable so each supervisor lands on their own channel.
    public function broadcastOn(mixed $notifiable = null): array
    {
        if (! $notifiable) {
            return [];
        }

        return [new PrivateChannel('carrier-supervisor.' . $notifiable->id)];
    }
}
