<?php

namespace App\Notifications\TravelAgency;

use App\Models\TravelAgencyChangeRequest;
use App\Notifications\BaseDatabaseBroadcastNotification;

class ChangeRequestApproved extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly TravelAgencyChangeRequest $changeRequest) {}

    public function notificationType(): string
    {
        return 'travel_agency_change_request_approved';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title' => 'Change Request Approved',
            'message' => "Your change request for {$this->changeRequest->section} has been approved.",
            'url' => route('travel-agency.change-requests.index'),
            'change_request_id' => $this->changeRequest->id,
        ];
    }

    public function broadcastOn(): array
    {
        return [];
    }
}
