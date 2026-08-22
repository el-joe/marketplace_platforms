<?php

namespace App\Notifications\Carrier;

use App\Models\Shipment;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;

class NewUnassignedShipmentArrived extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly Shipment $shipment) {}

    public function notificationType(): string
    {
        return 'new_unassigned_shipment_arrived';
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'push'];
    }

    public function toPush(object $notifiable): array
    {
        return [
            'title' => 'New Unassigned Shipment',
            'body'  => 'New shipment awaiting agent assignment',
            'data'  => [
                'screen' => 'unassigned_shipments',
                'id'     => null,
            ],
        ];
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title'       => 'New Unassigned Shipment',
            'message'     => "Shipment #{$this->shipment->tracking_number} is awaiting agent assignment.",
            'url'         => route('carrier.assignments.unassigned'),
            'shipment_id' => $this->shipment->id,
            'tracking_no' => $this->shipment->tracking_number,
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
