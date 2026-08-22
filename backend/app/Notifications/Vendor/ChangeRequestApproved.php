<?php

namespace App\Notifications\Vendor;

use App\Models\VendorChangeRequest;
use App\Notifications\BaseDatabaseBroadcastNotification;

class ChangeRequestApproved extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly VendorChangeRequest $changeRequest) {}

    public function notificationType(): string
    {
        return 'vendor_change_request_approved';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title' => 'Change Request Approved',
            'message' => "Your change request for {$this->changeRequest->section} has been approved.",
            'url' => route('partner.change-requests.show', $this->changeRequest->id),
            'change_request_id' => $this->changeRequest->id,
        ];
    }

    public function broadcastOn(): array
    {
        return [];
    }
}
