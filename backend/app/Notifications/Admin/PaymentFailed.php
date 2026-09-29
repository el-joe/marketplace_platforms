<?php

namespace App\Notifications\Admin;

use App\Models\Order;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Notifications\Messages\MailMessage;

class PaymentFailed extends BaseDatabaseBroadcastNotification
{
    public function __construct(
        private readonly Order $order,
        private readonly string $failureMessage = '',
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    public function notificationType(): string
    {
        return 'payment_failed';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title' => 'Payment Failed',
            'message' => "Payment failed for order #{$this->order->order_number}.",
            'url' => route('admin.orders.show', $this->order),
            'order_id' => $this->order->id,
            'failure_message' => $this->failureMessage,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Payment Failed — Order #{$this->order->order_number}")
            ->line("Payment failed for order #{$this->order->order_number}.")
            ->action('View Order', route('admin.orders.show', $this->order));
    }

    public function broadcastOn(mixed $notifiable = null): array
    {
        if (! $notifiable) {
            return [];
        }

        return [new PrivateChannel('admin.'.$notifiable->id)];
    }
}
