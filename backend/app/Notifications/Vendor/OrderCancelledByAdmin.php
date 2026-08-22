<?php

namespace App\Notifications\Vendor;

use App\Models\SubOrder;
use App\Notifications\BaseDatabaseBroadcastNotification;

class OrderCancelledByAdmin extends BaseDatabaseBroadcastNotification
{
    public function __construct(
        private readonly SubOrder $subOrder,
        private readonly string $reason = ''
    ) {}

    public function notificationType(): string
    {
        return 'order_cancelled_by_admin';
    }

    public function notificationData(object $notifiable): array
    {
        $message = "Order #{$this->subOrder->sub_order_number} has been cancelled by the platform.";
        if ($this->reason) {
            $message .= " Reason: {$this->reason}";
        }

        return [
            'title'        => 'Order Cancelled by Admin',
            'message'      => $message,
            'url'          => route('partner.orders.show', $this->subOrder->sub_order_number),
            'sub_order_id' => $this->subOrder->id,
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
