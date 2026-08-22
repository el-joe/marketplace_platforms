<?php

namespace App\Notifications\Carrier;

use App\Models\DeliveryAssignment;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;

class DeliveryCompleted extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly DeliveryAssignment $assignment) {}

    public function notificationType(): string
    {
        return 'delivery_completed';
    }

    public function notificationData(object $notifiable): array
    {
        $agent = $this->assignment->agent;
        $subOrderNumber = $this->assignment->subOrder?->sub_order_number ?? $this->assignment->sub_order_id;

        return [
            'title'             => 'Delivery Completed',
            'message'           => "Order {$subOrderNumber} delivered by {$agent->name}",
            'url'               => route('carrier.assignments.show', $this->assignment->id),
            'assignment_id'     => $this->assignment->id,
            'agent_id'          => $agent->id,
            'agent_name'        => $agent->name,
            'sub_order_number'  => $subOrderNumber,
        ];
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'push'];
    }

    public function toPush(object $notifiable): array
    {
        $agent = $this->assignment->agent;
        $subOrderNumber = $this->assignment->subOrder?->sub_order_number ?? $this->assignment->sub_order_id;

        return [
            'title' => 'Delivery Completed',
            'body'  => "Order {$subOrderNumber} delivered by {$agent->name}",
            'data'  => [
                'screen' => 'agent_assignments',
                'id'     => $agent->id,
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
