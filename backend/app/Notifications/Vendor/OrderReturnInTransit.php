<?php

namespace App\Notifications\Vendor;

use App\Models\SubOrder;
use App\Notifications\BaseDatabaseBroadcastNotification;

class OrderReturnInTransit extends BaseDatabaseBroadcastNotification
{
    public function __construct(
        private readonly SubOrder $subOrder,
    ) {}

    public function notificationType(): string
    {
        return 'order_return_in_transit';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title'        => 'Order Return In Transit',
            'message'      => "The customer refused delivery for order #{$this->subOrder->sub_order_number}. "
                . 'The package is being returned to you.',
            'url'          => route('partner.orders.show', $this->subOrder->sub_order_number),
            'sub_order_id' => $this->subOrder->id,
        ];
    }

    public function broadcastOn(): array
    {
        return [];
    }
}
