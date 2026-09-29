<?php

namespace App\Notifications\Admin;

use App\Models\Order;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;

class OrderCancelledByCustomer extends BaseDatabaseBroadcastNotification
{
    public function __construct(
        private readonly Order $order,
        private readonly string $reason = '',
    ) {}

    public function notificationType(): string
    {
        return 'order_cancelled_by_customer';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title' => 'Order Cancelled by Customer',
            'message' => "Order #{$this->order->order_number} was cancelled by the customer.",
            'url' => route('admin.orders.show', $this->order),
            'order_id' => $this->order->id,
            'reason' => $this->reason,
        ];
    }

    public function broadcastOn(mixed $notifiable = null): array
    {
        if (! $notifiable) {
            return [];
        }

        return [new PrivateChannel('admin.'.$notifiable->id)];
    }
}
