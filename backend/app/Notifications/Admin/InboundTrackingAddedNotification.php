<?php

namespace App\Notifications\Admin;

use App\Models\FbnInboundRequest;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;

class InboundTrackingAddedNotification extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly FbnInboundRequest $inboundRequest) {}

    public function notificationType(): string
    {
        return 'fbn_inbound_tracking_added';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title' => 'FBN Tracking Number Added',
            'message' => "Vendor added tracking number for inbound request \"{$this->inboundRequest->request_number}\".",
            'inbound_request_id' => $this->inboundRequest->id,
            'link' => route('admin.fbn.inbound.index'),
        ];
    }

    public function broadcastOn(mixed $notifiable = null): array
    {
        if (! $notifiable) {
            return [];
        }

        return [new PrivateChannel('admin.' . $notifiable->id)];
    }
}
