<?php

namespace App\Notifications\Admin;

use App\Models\Order;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;

class NewOrderPlaced extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly Order $order) {}

    public function notificationType(): string
    {
        return 'new_order_placed';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title' => 'New Order Placed',
            'message' => "New order #{$this->order->order_number} placed by {$this->order->customer->name}",
            'url' => route('admin.orders.show', $this->order),
            'order_id' => $this->order->id,
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
