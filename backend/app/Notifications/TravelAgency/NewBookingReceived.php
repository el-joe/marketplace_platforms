<?php

namespace App\Notifications\TravelAgency;

use App\Models\TravelBooking;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;

class NewBookingReceived extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly TravelBooking $booking) {}

    public function notificationType(): string
    {
        return 'new_booking_received';
    }

    public function notificationData(object $notifiable): array
    {
        $package = $this->booking->package;

        return [
            'title'          => 'New Booking Received',
            'message'        => "Booking #{$this->booking->booking_number} for \"{$package->title_en}\" has been placed.",
            'url'            => route('travel-agency.bookings.show', $this->booking->id),
            'booking_id'     => $this->booking->id,
            'booking_number' => $this->booking->booking_number,
            'package_id'     => $package->id,
            'package_title'  => $package->title_en,
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('travel-agency.' . $this->booking->package->travel_agency_id)];
    }
}
