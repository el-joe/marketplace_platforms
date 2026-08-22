<?php

namespace App\Notifications\Vendor;

use App\Notifications\BaseDatabaseBroadcastNotification;

class AccountSuspended extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly string $reason = '') {}

    public function notificationType(): string
    {
        return 'account_suspended';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title'   => 'Account Suspended',
            'message' => 'Your vendor account has been suspended.' . ($this->reason ? ' Reason: ' . $this->reason : ''),
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
