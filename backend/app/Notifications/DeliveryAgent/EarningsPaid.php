<?php

namespace App\Notifications\DeliveryAgent;

use App\Models\DeliveryAgentPayout;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Notifications\Messages\MailMessage;

class EarningsPaid extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly DeliveryAgentPayout $payout) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    public function notificationType(): string
    {
        return 'earnings_paid';
    }

    public function notificationData(object $notifiable): array
    {
        $amount = number_format($this->payout->net_amount / 100, 2).' '.$this->payout->currency;

        return [
            'title' => 'Earnings Paid',
            'message' => "Your earnings payout of {$amount} has been processed.",
            'url' => route('delivery.earnings.index'),
            'amount' => $this->payout->net_amount,
            'payout_id' => $this->payout->id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount = number_format($this->payout->net_amount / 100, 2).' '.$this->payout->currency;

        return (new MailMessage)
            ->subject('Earnings Paid')
            ->line("Your earnings payout of {$amount} has been processed.")
            ->action('View Earnings', route('delivery.earnings.index'));
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('delivery-agent.'.$this->payout->agent_id)];
    }
}
