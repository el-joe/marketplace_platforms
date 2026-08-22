<?php

namespace App\Notifications\Vendor;

use App\Notifications\BaseDatabaseBroadcastNotification;

class PayoutHoldReleased extends BaseDatabaseBroadcastNotification
{
    public function notificationType(): string
    {
        return 'payout_hold_released';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title'   => 'Payout Hold Released',
            'message' => 'Your payout hold has been released. Payouts will resume on the next cycle.',
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
