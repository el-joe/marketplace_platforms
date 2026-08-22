<?php

namespace App\Notifications\TravelAgency;

use App\Models\TravelPackage;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;

class LowSeatsRemaining extends BaseDatabaseBroadcastNotification
{
    public function __construct(
        private readonly TravelPackage $package,
        private readonly int $seatsRemaining,
    ) {}

    public function notificationType(): string
    {
        return 'low_seats_remaining';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title'           => 'Low Seats Remaining',
            'message'         => "Only {$this->seatsRemaining} seat(s) left on \"{$this->package->title_en}\".",
            'url'             => route('travel-agency.packages.show', $this->package->id),
            'package_id'      => $this->package->id,
            'package_title'   => $this->package->title_en,
            'seats_remaining' => $this->seatsRemaining,
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('travel-agency.' . $this->package->travel_agency_id)];
    }
}
