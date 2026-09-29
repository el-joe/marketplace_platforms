<?php

namespace App\Notifications\Vendor;

use App\Models\SubOrder;
use App\Notifications\BaseDatabaseBroadcastNotification;

class SubOrderCancelledByCustomer extends BaseDatabaseBroadcastNotification
{
    public function __construct(
        private readonly SubOrder $subOrder,
        private readonly string $reason = ''
    ) {}

    public function notificationType(): string
    {
        return 'sub_order_cancelled_by_customer';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title' => 'Order Cancelled by Customer',
            'message' => "Sub-order #{$this->subOrder->sub_order_number} was cancelled by the customer.",
            'url' => route('partner.orders.show', $this->subOrder->sub_order_number),
            'sub_order_id' => $this->subOrder->id,
            'reason' => $this->reason,
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
            'body' => $data['message'],
            'data' => [
                'screen' => 'order_detail',
                'id' => $this->subOrder->sub_order_number,
                'type' => class_basename(static::class),
            ],
        ];
    }

    public function broadcastOn(): array
    {
        return [];
    }
}
