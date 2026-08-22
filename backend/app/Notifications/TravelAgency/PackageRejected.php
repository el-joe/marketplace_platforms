<?php

namespace App\Notifications\TravelAgency;

use App\Models\TravelPackage;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;

class PackageRejected extends BaseDatabaseBroadcastNotification
{
    public function __construct(
        private readonly TravelPackage $package,
        private readonly string $reason,
    ) {}

    public function notificationType(): string
    {
        return 'package_rejected';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title'         => 'Package Returned for Revision',
            'message'       => "Your package \"{$this->package->title_en}\" was returned to draft. Reason: {$this->reason}",
            'url'           => route('travel-agency.packages.edit', $this->package->id),
            'package_id'    => $this->package->id,
            'package_title' => $this->package->title_en,
            'reason'        => $this->reason,
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('travel-agency.' . $this->package->travel_agency_id)];
    }
}
