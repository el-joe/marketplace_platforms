<?php

namespace App\Notifications\Customer;

use App\Models\SubOrder;
use App\Support\StorefrontUrl;
use Illuminate\Broadcasting\PrivateChannel;

class OrderCancelled extends BaseCustomerNotification
{
    public function __construct(
        private readonly SubOrder $subOrder,
        private readonly ?string $reason = null,
        private readonly int $cancelledItemCount = 0,
    ) {}

    public function notificationType(): string
    {
        return 'order_cancelled';
    }

    public function notificationData(object $notifiable): array
    {
        // A positive item count means only part of the sub-order was cancelled; the rest stays active.
        $isPartial = $this->cancelledItemCount > 0;
        $number = $this->subOrder->sub_order_number;

        $message = $isPartial
            ? "{$this->cancelledItemCount} item(s) in your order #{$number} have been cancelled. The rest of your order is still being processed."
            : "Your order #{$number} has been cancelled.";
        $messageAr = $isPartial
            ? "تم إلغاء {$this->cancelledItemCount} من المنتجات في طلبك رقم #{$number}. باقي الطلب لا يزال قيد التنفيذ."
            : "تم إلغاء طلبك رقم #{$number}.";
        if ($this->reason) {
            $message .= " Reason: {$this->reason}";
            $messageAr .= " السبب: {$this->reason}";
        }

        return [
            'title' => $isPartial ? 'Items Cancelled' : 'Order Cancelled',
            'title_ar' => $isPartial ? 'تم إلغاء منتجات من طلبك' : 'تم إلغاء الطلب',
            'message' => $message,
            'message_ar' => $messageAr,
            'url' => StorefrontUrl::order($this->subOrder->order, StorefrontUrl::languageOf($notifiable)),
            'sub_order_id' => $this->subOrder->id,
            'sub_order_number' => $this->subOrder->sub_order_number,
            'reason' => $this->reason,
            'partial' => $isPartial,
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('customer.'.$this->subOrder->order->customer_id)];
    }
}
