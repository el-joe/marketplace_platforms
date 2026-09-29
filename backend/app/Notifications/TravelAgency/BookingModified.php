<?php

namespace App\Notifications\TravelAgency;

use App\Models\TravelBooking;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;

class BookingModified extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly TravelBooking $booking) {}

    public function notificationType(): string
    {
        return 'booking_modified';
    }

    public function notificationData(object $notifiable): array
    {
        $ref = $this->booking->booking_number ?? $this->booking->id;

        return [
            'title' => 'Booking Modified',
            'message' => "Booking #{$ref} has been modified by the customer.",
            'url' => route('travel-agency.bookings.show', $this->booking->id),
            'booking_id' => $this->booking->id,
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('travel-agency.'.$this->booking->package->travel_agency_id)];
    }
}
