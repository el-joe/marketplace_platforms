<?php

namespace App\Notifications\Customer;

use App\Models\Order;
use App\Support\StorefrontUrl;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Notifications\Messages\MailMessage;

class PaymentFailed extends BaseCustomerNotification
{
    public function __construct(
        private readonly Order $order,
        private readonly string $failureMessage = '',
    ) {}

    public function notificationType(): string
    {
        return 'payment_failed';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title' => 'Payment Failed',
            'title_ar' => 'فشل الدفع',
            'message' => "Your payment for order #{$this->order->order_number} could not be processed. {$this->failureMessage}",
            'url' => StorefrontUrl::order($this->order, StorefrontUrl::languageOf($notifiable)),
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'failure_message' => $this->failureMessage,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Payment Failed — Order #'.$this->order->order_number)
            ->line('Your payment could not be processed.')
            ->line($this->failureMessage)
            ->action('Try Again', StorefrontUrl::order($this->order, StorefrontUrl::languageOf($notifiable)));
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('customer.'.$notifiable->id)];
    }
}
