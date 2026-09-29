<?php

namespace App\Notifications\Carrier;

use App\Models\DeliveryAgent;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;

class NewAgentRegistered extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly DeliveryAgent $agent) {}

    public function notificationType(): string
    {
        return 'new_agent_registered';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title' => 'New Agent Registered',
            'message' => "New delivery agent {$this->agent->name} has joined your company.",
            'url' => route('carrier.reports.earnings'),
            'agent_id' => $this->agent->id,
            'agent_name' => $this->agent->name,
        ];
    }

    public function broadcastOn(mixed $notifiable = null): array
    {
        if (! $notifiable) {
            return [];
        }

        return [new PrivateChannel('carrier-supervisor.'.$notifiable->id)];
    }
}
