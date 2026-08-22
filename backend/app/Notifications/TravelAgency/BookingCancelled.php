<?php

namespace App\Notifications\TravelAgency;

use App\Models\TravelBooking;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;

class BookingCancelled extends BaseDatabaseBroadcastNotification
{
    public function __construct(
        private readonly TravelBooking $booking,
        private readonly string $cancelledBy = 'customer',
    ) {}

    public function notificationType(): string
    {
        return 'booking_cancelled';
    }

    public function notificationData(object $notifiable): array
    {
        $package = $this->booking->package;
        $actor   = $this->cancelledBy === 'admin' ? 'an admin' : 'the customer';

        return [
            'title'          => 'Booking Cancelled',
            'message'        => "Booking #{$this->booking->booking_number} for \"{$package->title_en}\" was cancelled by {$actor}.",
            'url'            => route('travel-agency.bookings.show', $this->booking->id),
            'booking_id'     => $this->booking->id,
            'booking_number' => $this->booking->booking_number,
            'package_id'     => $package->id,
            'cancelled_by'   => $this->cancelledBy,
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('travel-agency.' . $this->booking->package->travel_agency_id)];
    }
}
