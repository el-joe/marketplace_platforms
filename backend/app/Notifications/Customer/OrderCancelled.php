<?php

namespace App\Notifications\Customer;

use App\Models\SubOrder;
use Illuminate\Broadcasting\PrivateChannel;

class OrderCancelled extends BaseCustomerNotification
{
    public function __construct(
        private readonly SubOrder $subOrder,
        private readonly ?string $reason = null,
    ) {}

    public function notificationType(): string
    {
        return 'order_cancelled';
    }

    public function notificationData(object $notifiable): array
    {
        $message = "Your order #{$this->subOrder->sub_order_number} has been cancelled.";
        $messageAr = "تم إلغاء طلبك رقم #{$this->subOrder->sub_order_number}.";
        if ($this->reason) {
            $message .= " Reason: {$this->reason}";
            $messageAr .= " السبب: {$this->reason}";
        }

        return [
            'title'            => 'Order Cancelled',
            'title_ar'         => 'تم إلغاء الطلب',
            'message'          => $message,
            'message_ar'       => $messageAr,
            'url'              => route('customer.orders.show', $this->subOrder->order->order_number),
            'sub_order_id'     => $this->subOrder->id,
            'sub_order_number' => $this->subOrder->sub_order_number,
            'reason'           => $this->reason,
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('customer.' . $this->subOrder->order->customer_id)];
    }
}
