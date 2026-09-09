<?php

namespace App\Notifications\Marketer;

use App\Models\WalletWithdrawalRequest;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;

class PayoutProcessedNotification extends BaseDatabaseBroadcastNotification
{
    public function __construct(
        public readonly WalletWithdrawalRequest $withdrawal,
        public readonly string $marketerAdminId,
    ) {}

    public function notificationType(): string
    {
        return 'payout_processed';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'withdrawal_id' => $this->withdrawal->id,
            'amount'        => $this->withdrawal->amount,
            'currency'      => $this->withdrawal->currency,
            'title'         => 'تم صرف الدفعة',
            'message'       => 'تم صرف طلب السحب الخاص بك بمبلغ ' . $this->withdrawal->amount . ' ' . $this->withdrawal->currency . '.',
            'url'           => route('marketer.finance.wallet'),
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('marketer.' . $this->marketerAdminId)];
    }
}
