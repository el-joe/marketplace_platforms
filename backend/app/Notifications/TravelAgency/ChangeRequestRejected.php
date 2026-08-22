<?php

namespace App\Notifications\TravelAgency;

use App\Models\TravelAgencyChangeRequest;
use App\Notifications\BaseDatabaseBroadcastNotification;

class ChangeRequestRejected extends BaseDatabaseBroadcastNotification
{
    public function __construct(
        private readonly TravelAgencyChangeRequest $changeRequest,
        private readonly string $reason = '',
    ) {}

    public function notificationType(): string
    {
        return 'travel_agency_change_request_rejected';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title' => 'Change Request Rejected',
            'message' => "Your change request for {$this->changeRequest->section} was rejected." . ($this->reason ? " Reason: {$this->reason}" : ''),
            'url' => route('travel-agency.change-requests.index'),
            'change_request_id' => $this->changeRequest->id,
        ];
    }

    public function broadcastOn(): array
    {
        return [];
    }
}
