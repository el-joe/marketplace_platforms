<?php

namespace App\Notifications\Vendor;

use App\Models\VendorStrike;
use App\Notifications\BaseDatabaseBroadcastNotification;

class StrikeIssued extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly VendorStrike $strike) {}

    public function notificationType(): string
    {
        return 'strike_issued';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title'     => 'Policy Strike Issued',
            'message'   => 'A ' . $this->strike->severity?->value . ' strike has been issued on your account. Reason: ' . $this->strike->reason?->value,
            'url'       => route('partner.dashboard'),
            'strike_id' => $this->strike->id,
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
