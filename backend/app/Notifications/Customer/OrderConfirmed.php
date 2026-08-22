<?php

namespace App\Notifications\Customer;

use App\Models\SubOrder;
use Illuminate\Broadcasting\PrivateChannel;

class OrderConfirmed extends BaseCustomerNotification
{
    public function __construct(private readonly SubOrder $subOrder) {}

    public function notificationType(): string
    {
        return 'order_confirmed';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title'          => 'Order Confirmed',
            'title_ar'       => 'تم تأكيد الطلب',
            'message'        => "Your order #{$this->subOrder->sub_order_number} has been confirmed and is being prepared.",
            'message_ar'     => "تم تأكيد طلبك رقم #{$this->subOrder->sub_order_number} وجاري تجهيزه.",
            'url'            => route('customer.orders.show', $this->subOrder->order->order_number),
            'sub_order_id'   => $this->subOrder->id,
            'sub_order_number' => $this->subOrder->sub_order_number,
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('customer.' . $this->subOrder->order->customer_id)];
    }
}
