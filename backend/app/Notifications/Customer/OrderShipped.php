<?php

namespace App\Notifications\Customer;

use App\Models\SubOrder;
use App\Support\StorefrontUrl;
use Illuminate\Broadcasting\PrivateChannel;

class OrderShipped extends BaseCustomerNotification
{
    public function __construct(private readonly SubOrder $subOrder) {}

    public function notificationType(): string
    {
        return 'order_shipped';
    }

    public function notificationData(object $notifiable): array
    {
        $tracking = $this->subOrder->tracking_number ?? null;

        return [
            'title' => 'Order Shipped',
            'title_ar' => 'تم شحن الطلب',
            'message' => "Your order #{$this->subOrder->sub_order_number} is on its way!"
                .($tracking ? " Tracking number: {$tracking}." : ''),
            'message_ar' => "طلبك رقم #{$this->subOrder->sub_order_number} في الطريق إليك!"
                .($tracking ? " رقم التتبع: {$tracking}." : ''),
            'url' => StorefrontUrl::order($this->subOrder->order, StorefrontUrl::languageOf($notifiable)),
            'sub_order_id' => $this->subOrder->id,
            'sub_order_number' => $this->subOrder->sub_order_number,
            'tracking_number' => $tracking,
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('customer.'.$this->subOrder->order->customer_id)];
    }
}
