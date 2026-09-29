<?php

namespace App\Notifications\Vendor;

use App\Models\SubOrder;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Notifications\Messages\MailMessage;

class PaymentCapturedForOrder extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly SubOrder $subOrder) {}

    public function notificationType(): string
    {
        return 'payment_captured_for_order';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title' => 'Payment Received',
            'message' => "Payment captured for order #{$this->subOrder->sub_order_number}.",
            'url' => route('partner.orders.show', $this->subOrder->sub_order_number),
            'sub_order_id' => $this->subOrder->id,
            'order_id' => $this->subOrder->order_id,
        ];
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'push', 'mail'];
    }

    public function toPush(object $notifiable): array
    {
        $data = $this->notificationData($notifiable);

        return [
            'title' => $data['title'],
            'body' => $data['message'],
            'data' => [
                'screen' => 'order_detail',
                'id' => $this->subOrder->sub_order_number,
                'type' => class_basename(static::class),
            ],
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Payment Received — Order #{$this->subOrder->sub_order_number}")
            ->line("Payment has been captured for sub-order #{$this->subOrder->sub_order_number}.")
            ->action('View Order', route('partner.orders.show', $this->subOrder->sub_order_number));
    }

    public function broadcastOn(): array
    {
        return [];
    }
}
