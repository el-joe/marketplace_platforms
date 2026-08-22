<?php

namespace App\Notifications\Customer;

use App\Models\SubOrder;
use Illuminate\Broadcasting\PrivateChannel;

class OrderOutForDelivery extends BaseCustomerNotification
{
    public function __construct(
        private readonly SubOrder $subOrder,
        private readonly ?string $deliveryOtp = null,
    ) {}

    public function notificationType(): string
    {
        return 'order_out_for_delivery';
    }

    public function notificationData(object $notifiable): array
    {
        $message = $this->deliveryOtp
            ? "Your order #{$this->subOrder->sub_order_number} is out for delivery. Your OTP is: {$this->deliveryOtp}"
            : "Your order #{$this->subOrder->sub_order_number} is out for delivery today.";

        $messageAr = $this->deliveryOtp
            ? "طلبك رقم #{$this->subOrder->sub_order_number} في الطريق إليك. رمز التحقق: {$this->deliveryOtp}"
            : "طلبك رقم #{$this->subOrder->sub_order_number} في الطريق إليك اليوم.";

        return [
            'title'            => 'Out for Delivery',
            'title_ar'         => 'الطلب في الطريق إليك',
            'message'          => $message,
            'message_ar'       => $messageAr,
            'url'              => route('customer.orders.show', $this->subOrder->order->order_number),
            'sub_order_id'     => $this->subOrder->id,
            'sub_order_number' => $this->subOrder->sub_order_number,
            'delivery_otp'     => $this->deliveryOtp,
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('customer.' . $this->subOrder->order->customer_id)];
    }
}
