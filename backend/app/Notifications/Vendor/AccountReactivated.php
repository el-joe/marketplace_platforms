<?php

namespace App\Notifications\Vendor;

use App\Notifications\BaseDatabaseBroadcastNotification;

class AccountReactivated extends BaseDatabaseBroadcastNotification
{
    public function notificationType(): string
    {
        return 'account_reactivated';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title'   => 'Account Reactivated',
            'message' => 'Your vendor account has been reactivated. You can now resume selling on the platform.',
            'url'     => route('partner.dashboard'),
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
                'screen' => 'dashboard',
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
