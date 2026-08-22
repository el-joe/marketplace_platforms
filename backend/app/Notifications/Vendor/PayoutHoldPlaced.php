<?php

namespace App\Notifications\Vendor;

use App\Notifications\BaseDatabaseBroadcastNotification;

class PayoutHoldPlaced extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly string $reason = '') {}

    public function notificationType(): string
    {
        return 'payout_hold_placed';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title'   => 'Payout Hold Placed',
            'message' => 'A hold has been placed on your payouts.' . ($this->reason ? ' Reason: ' . $this->reason : ''),
            'url'     => route('partner.payouts.index'),
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
                'screen' => 'payout_list',
                'id'     => null,
                'type'   => class_basename(static::class),
            ],
        ];
    }

    public function broadcastOn(): array
    {
        return [];
    }
}
