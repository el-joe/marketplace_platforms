<?php

namespace App\Notifications\Customer;

use Illuminate\Broadcasting\PrivateChannel;

class WalletCredited extends BaseCustomerNotification
{
    public function __construct(
        private readonly int|float $amountCents,
        private readonly string $reason = '',
        private readonly ?string $customerId = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function notificationType(): string
    {
        return 'wallet_credited';
    }

    public function notificationData(object $notifiable): array
    {
        $amountFormatted = \App\Support\Money::format($this->amountCents);

        return [
            'title' => 'Wallet Credited',
            'title_ar' => 'تم إضافة رصيد للمحفظة',
            'message' => "Your wallet has been credited with {$amountFormatted}. {$this->reason}",
            'url' => '#',
            'amount_cents' => \App\Support\Money::number($this->amountCents),
            'amount_formatted' => $amountFormatted,
            'reason' => $this->reason,
        ];
    }

    public function broadcastOn(): array
    {
        // broadcastOn() receives no notifiable, so the channel owner is passed in. Returning [] lets Laravel fall back
        // to the notifiable's default channel instead of throwing.
        return $this->customerId === null ? [] : [new PrivateChannel('customer.'.$this->customerId)];
    }
}
