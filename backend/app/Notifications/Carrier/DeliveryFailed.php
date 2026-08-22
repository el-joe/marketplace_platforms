<?php

namespace App\Notifications\Carrier;

use App\Models\DeliveryAssignment;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;

class DeliveryFailed extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly DeliveryAssignment $assignment) {}

    public function notificationType(): string
    {
        return 'delivery_failed';
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'push'];
    }

    public function toPush(object $notifiable): array
    {
        $agent = $this->assignment->agent;

        return [
            'title' => 'Delivery Failed',
            'body'  => "Agent {$agent->name} failed to deliver order #{$this->assignment->id}.",
            'data'  => [
                'screen' => 'agent_assignments',
                'id'     => $agent->id,
            ],
        ];
    }

    public function notificationData(object $notifiable): array
    {
        $agent = $this->assignment->agent;
        $reason = $this->assignment->failure_reason ?? 'unknown';

        return [
            'title'          => 'Delivery Failed',
            'message'        => "Agent {$agent->name} marked assignment #{$this->assignment->id} as failed ({$reason}). Reassignment may be needed.",
            'url'            => route('carrier.assignments.show', $this->assignment->id),
            'assignment_id'  => $this->assignment->id,
            'agent_id'       => $agent->id,
            'agent_name'     => $agent->name,
            'failure_reason' => $reason,
            'failure_notes'  => $this->assignment->failure_notes,
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
