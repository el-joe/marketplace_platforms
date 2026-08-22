<?php

namespace App\Notifications\Vendor;

use App\Models\SubOrder;
use App\Notifications\BaseDatabaseBroadcastNotification;

class NewOrderReceived extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly SubOrder $subOrder) {}

    public function notificationType(): string
    {
        return 'new_order_received';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title'         => 'New Order Received',
            'message'       => "Order #{$this->subOrder->sub_order_number} has been placed and is awaiting confirmation.",
            'url'           => route('partner.orders.show', $this->subOrder->sub_order_number),
            'sub_order_id'  => $this->subOrder->id,
        ];
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'push'];
    }

    public function toPush(object $notifiable): array
    {
        $data = $this->notificationData($notifiable);

        return [
            'title' => $data['title'],
            'body'  => $data['message'],
            'data'  => [
                'screen' => 'order_detail',
                'id'     => $this->subOrder->sub_order_number,
                'type'   => class_basename(static::class),
            ],
        ];
    }

    public function broadcastOn(): array
    {
        return [];
    }
}
