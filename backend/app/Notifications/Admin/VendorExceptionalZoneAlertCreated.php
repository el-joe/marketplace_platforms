<?php

namespace App\Notifications\Admin;

use App\Models\Vendor;
use App\Models\Warehouse;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;

class VendorExceptionalZoneAlertCreated extends BaseDatabaseBroadcastNotification
{
    public function __construct(
        private readonly Vendor $vendor,
        private readonly Warehouse $warehouse,
        private readonly int $cityCount,
    ) {}

    public function notificationType(): string
    {
        return 'vendor_exceptional_zone_alert_created';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title' => 'Vendor Exceptional Zone Alert',
            'message' => "Vendor \"{$this->vendor->store_name}\" reported {$this->cityCount} exceptional " . str('city')->plural($this->cityCount) . " from warehouse {$this->warehouse->name}.",
            'link' => route('admin.shipping-subsidies.alerts.index'),
            'vendor_id' => $this->vendor->id,
            'warehouse_id' => $this->warehouse->id,
        ];
    }

    public function broadcastOn(mixed $notifiable = null): array
    {
        if (! $notifiable) {
            return [];
        }

        return [new PrivateChannel('admin.' . $notifiable->id)];
    }
}
