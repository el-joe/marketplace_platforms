<?php

namespace App\Notifications\Vendor;

use App\Models\VendorChangeRequest;
use App\Notifications\BaseDatabaseBroadcastNotification;

class ChangeRequestRejected extends BaseDatabaseBroadcastNotification
{
    public function __construct(
        private readonly VendorChangeRequest $changeRequest,
        private readonly string $reason = '',
    ) {}

    public function notificationType(): string
    {
        return 'vendor_change_request_rejected';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title' => 'Change Request Rejected',
            'message' => "Your change request for {$this->changeRequest->section} was rejected." . ($this->reason ? " Reason: {$this->reason}" : ''),
            'url' => route('partner.change-requests.show', $this->changeRequest->id),
            'change_request_id' => $this->changeRequest->id,
        ];
    }

    public function broadcastOn(): array
    {
        return [];
    }
}
