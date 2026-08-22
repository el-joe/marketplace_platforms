<?php

namespace App\Notifications\Admin;

use App\Models\TravelAgencyChangeRequest;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;

class TravelAgencyChangeRequestSubmitted extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly TravelAgencyChangeRequest $changeRequest) {}

    public function notificationType(): string
    {
        return 'travel_agency_change_request_submitted';
    }

    public function notificationData(object $notifiable): array
    {
        $agencyName = $this->changeRequest->travelAgency->name;

        return [
            'title' => 'Travel Agency Change Request Pending Review',
            'message' => "{$agencyName} submitted a change request for {$this->changeRequest->section}.",
            'url' => route('admin.travel.change-requests.show', $this->changeRequest->id),
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
