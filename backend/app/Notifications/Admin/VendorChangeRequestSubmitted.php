<?php

namespace App\Notifications\Admin;

use App\Models\VendorChangeRequest;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;

class VendorChangeRequestSubmitted extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly VendorChangeRequest $changeRequest) {}

    public function notificationType(): string
    {
        return 'vendor_change_request_submitted';
    }

    public function notificationData(object $notifiable): array
    {
        $vendorName = $this->changeRequest->vendor->store_name;

        return [
            'title' => 'Vendor Change Request Pending Review',
            'message' => "{$vendorName} submitted a change request for {$this->changeRequest->section}.",
            'url' => route('admin.vendor-change-requests.show', $this->changeRequest->id),
            'change_request_id' => $this->changeRequest->id,
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
