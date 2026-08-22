<?php

namespace App\Notifications\Admin;

use App\Models\Vendor;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;

class NewVendorApplicationSubmitted extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly Vendor $vendor) {}

    public function notificationType(): string
    {
        return 'new_vendor_application_submitted';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title'     => 'New Vendor Application',
            'message'   => "Vendor \"{$this->vendor->store_name}\" has submitted an application for review.",
            'vendor_id' => $this->vendor->id,
            'link'      => route('admin.vendor-applications.show', $this->vendor->id),
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
