<?php

namespace App\Notifications\Admin;

use App\Models\WalletWithdrawalRequest;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;

class WithdrawalRequested extends BaseDatabaseBroadcastNotification
{
    public function __construct(
        private readonly WalletWithdrawalRequest $withdrawalRequest,
    ) {}

    public function notificationType(): string
    {
        return 'wallet_withdrawal_requested';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title'                 => 'New Withdrawal Request',
            'message'               => "A withdrawal request for {$this->withdrawalRequest->amount} {$this->withdrawalRequest->currency} cents has been submitted.",
            'withdrawal_request_id' => $this->withdrawalRequest->id,
            'link'                  => route('admin.wallets.show', $this->withdrawalRequest->wallet_id),
        ];
    }

    public function broadcastOn(mixed $notifiable = null): array
    {
        if (! $notifiable) {
            return [];
        }

        return [new PrivateChannel('admin.' . $notifiable->id)];
    }
}
