<?php

namespace App\Notifications\TravelAgency;

use App\Models\TravelPackage;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;

class PackageApproved extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly TravelPackage $package) {}

    public function notificationType(): string
    {
        return 'package_approved';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title'         => 'Package Approved',
            'message'       => "Your package \"{$this->package->title_en}\" has been approved and is now live.",
            'url'           => route('travel-agency.packages.show', $this->package->id),
            'package_id'    => $this->package->id,
            'package_title' => $this->package->title_en,
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('travel-agency.' . $this->package->travel_agency_id)];
    }
}
