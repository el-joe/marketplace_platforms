<?php

namespace App\Notifications\Customer;

use App\Models\SubOrder;
use Illuminate\Broadcasting\PrivateChannel;

class OrderDelivered extends BaseCustomerNotification
{
    public function __construct(private readonly SubOrder $subOrder) {}

    public function notificationType(): string
    {
        return 'order_delivered';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title'            => 'Order Delivered',
            'title_ar'         => 'تم تسليم الطلب',
            'message'          => "Your order #{$this->subOrder->sub_order_number} has been delivered. Enjoy your purchase!",
            'message_ar'       => "تم تسليم طلبك رقم #{$this->subOrder->sub_order_number}. نتمنى أن ينال إعجابك!",
            'url'              => route('customer.orders.show', $this->subOrder->order->order_number),
            'sub_order_id'     => $this->subOrder->id,
            'sub_order_number' => $this->subOrder->sub_order_number,
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('customer.' . $this->subOrder->order->customer_id)];
    }
}
