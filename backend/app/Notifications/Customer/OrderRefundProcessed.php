<?php

namespace App\Notifications\Customer;

use App\Models\Refund;
use Illuminate\Broadcasting\PrivateChannel;

class OrderRefundProcessed extends BaseCustomerNotification
{
    public function __construct(private readonly Refund $refund) {}

    public function notificationType(): string
    {
        return 'order_refund_processed';
    }

    public function notificationData(object $notifiable): array
    {
        $order = $this->refund->order;
        $currency = $this->refund->currency;
        $netAmount = number_format($this->refund->net_refund / 100, 2);

        $message = "Refund of {$currency} {$netAmount} processed";
        $messageAr = "تمت معالجة استرداد بقيمة {$currency} {$netAmount}";
        if ($this->refund->gateway_fee_deducted > 0) {
            $feeAmount = number_format(
                ($this->refund->gateway_fee_deducted + $this->refund->tax_deducted) / 100,
                2
            );
            $message .= " (gateway fee of {$currency} {$feeAmount} deducted)";
            $messageAr .= " (بعد خصم رسوم بوابة الدفع {$currency} {$feeAmount})";
        }
        $message .= '.';
        $messageAr .= '.';

        return [
            'title' => 'Refund Processed',
            'title_ar' => 'تمت معالجة المبلغ المسترد',
            'message' => $message,
            'message_ar' => $messageAr,
            'url' => route('customer.orders.show', $order->order_number),
            'refund_id' => $this->refund->id,
            'order_id' => $order->id,
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('customer.' . $this->refund->order->customer_id)];
    }
}
