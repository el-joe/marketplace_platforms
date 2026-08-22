<?php

namespace App\Notifications\Vendor;

use App\Models\Payout;
use App\Notifications\BaseDatabaseBroadcastNotification;

class PayoutProcessed extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly Payout $payout) {}

    public function notificationType(): string
    {
        return 'payout_processed';
    }

    public function notificationData(object $notifiable): array
    {
        $amount = number_format($this->payout->net_amount / 100, 2) . ' ' . $this->payout->currency;

        return [
            'title'     => 'Payout Approved',
            'message'   => "Your payout of {$amount} ({$this->payout->payout_number}) has been approved and will be transferred to your bank account.",
            'url'       => route('partner.payouts.show', $this->payout->payout_number),
            'payout_id' => $this->payout->id,
        ];
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'push'];
    }

    public function toPush(object $notifiable): array
    {
        $data = $this->notificationData($notifiable);

        return [
            'title' => $data['title'],
            'body'  => $data['message'],
            'data'  => [
                'screen' => 'payout_detail',
                'id'     => $this->payout->id,
                'type'   => class_basename(static::class),
            ],
        ];
    }

    public function broadcastOn(): array
    {
        return [];
    }
}
