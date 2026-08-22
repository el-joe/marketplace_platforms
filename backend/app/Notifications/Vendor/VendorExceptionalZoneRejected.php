<?php

namespace App\Notifications\Vendor;

use App\Models\VendorExceptionalZoneAlert;
use App\Notifications\BaseDatabaseBroadcastNotification;

class VendorExceptionalZoneRejected extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly VendorExceptionalZoneAlert $alert) {}

    public function notificationType(): string
    {
        return 'vendor_exceptional_zone_rejected';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title' => 'Exceptional Zone Alert Rejected',
            'message' => "Your exceptional zone alert was not approved. Reason: {$this->alert->admin_note}",
            'url' => route('partner.exceptional-zone-alerts.index'),
            'alert_id' => $this->alert->id,
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
            'body' => $data['message'],
            'data' => [
                'screen' => 'exceptional_zone_alerts',
                'id' => $this->alert->id,
                'type' => class_basename(static::class),
            ],
        ];
    }

    public function broadcastOn(): array
    {
        return [];
    }
}
