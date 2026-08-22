<?php

namespace App\Notifications\Customer;

use Illuminate\Broadcasting\PrivateChannel;

class SendOtpNotification extends BaseCustomerNotification
{
    public function __construct(
        private readonly string $customerId,
        private readonly string $otp,
        private readonly string $purpose,
    ) {}

    public function notificationType(): string
    {
        return 'security_otp';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title' => 'Your verification code',
            'title_ar' => 'رمز التحقق الخاص بك',
            'message' => "Your {$this->purpose} code is {$this->otp}. It expires in 15 minutes.",
            'message_ar' => "رمز التحقق الخاص بـ {$this->purpose} هو {$this->otp}. تنتهي صلاحيته خلال 15 دقيقة.",
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('customer.' . $this->customerId)];
    }
}
