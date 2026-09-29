<?php

namespace App\Notifications\Carrier;

use App\Models\DeliveryAgentPayout;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Notifications\Messages\MailMessage;

class PayoutProcessed extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly DeliveryAgentPayout $payout) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    public function notificationType(): string
    {
        return 'carrier_payout_processed';
    }

    public function notificationData(object $notifiable): array
    {
        $amount = number_format($this->payout->net_amount / 100, 2).' '.$this->payout->currency;
        $agentName = $this->payout->agent?->name ?? "Agent #{$this->payout->agent_id}";

        return [
            'title' => 'Agent Payout Processed',
            'message' => "Payout of {$amount} has been processed for {$agentName}.",
            'url' => route('carrier.reports.payouts'),
            'amount' => $this->payout->net_amount,
            'payout_id' => $this->payout->id,
            'payout_number' => $this->payout->payout_number,
            'agent_id' => $this->payout->agent_id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount = number_format($this->payout->net_amount / 100, 2).' '.$this->payout->currency;
        $agentName = $this->payout->agent?->name ?? "Agent #{$this->payout->agent_id}";

        return (new MailMessage)
            ->subject('Agent Payout Processed')
            ->line("Payout of {$amount} has been processed for your company ({$agentName}).")
            ->action('View Payouts', route('carrier.reports.payouts'));
    }

    public function broadcastOn(mixed $notifiable = null): array
    {
        if (! $notifiable) {
            return [];
        }

        return [new PrivateChannel('carrier-supervisor.'.$notifiable->id)];
    }
}
