<?php

namespace App\Notifications\Customer;

use App\Models\SubOrder;
use Illuminate\Broadcasting\PrivateChannel;

class OrderRefunded extends BaseCustomerNotification
{
    public function __construct(private readonly SubOrder $subOrder) {}

    public function notificationType(): string
    {
        return 'order_refunded';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title'            => 'Refund Processed',
            'title_ar'         => 'تمت معالجة المبلغ المسترد',
            'message'          => "A refund for your order #{$this->subOrder->sub_order_number} has been processed. Please allow a few business days for it to appear.",
            'message_ar'       => "تمت معالجة المبلغ المسترد لطلبك رقم #{$this->subOrder->sub_order_number}. يرجى الانتظار بضعة أيام عمل حتى يظهر المبلغ.",
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
