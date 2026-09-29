<?php

namespace App\Notifications\Marketer;

use App\Models\WalletWithdrawalRequest;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Notifies a marketer admin that a payout withdrawal has been scheduled.
 *
 * NOTE: A dedicated MarketerPayout model has not been implemented yet
 * (see FinancialReportService P-11/P-12 comments). This notification
 * uses WalletWithdrawalRequest as the closest existing payout model.
 * Update the constructor when MarketerPayout is introduced.
 */
class PayoutScheduled extends BaseDatabaseBroadcastNotification
{
    public function __construct(
        public readonly WalletWithdrawalRequest $withdrawal,
        public readonly string $marketerAdminId,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    public function notificationType(): string
    {
        return 'payout_scheduled';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title' => 'Payout Scheduled',
            'message' => "Your payout of {$this->withdrawal->amount} {$this->withdrawal->currency} has been scheduled.",
            'url' => route('marketer.finance.wallet'),
            'payout_id' => $this->withdrawal->id,
            'amount' => $this->withdrawal->amount,
            'scheduled_at' => $this->withdrawal->created_at?->toIso8601String(),
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('marketer.'.$this->marketerAdminId)];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Payout Scheduled')
            ->line("Your payout of {$this->withdrawal->amount} {$this->withdrawal->currency} has been scheduled.")
            ->action('View Wallet', route('marketer.finance.wallet'));
    }
}
