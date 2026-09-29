<?php

namespace App\Notifications\TravelAgency;

use App\Models\TravelBooking;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;

class PaymentReceived extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly TravelBooking $booking) {}

    public function notificationType(): string
    {
        return 'booking_payment_received';
    }

    public function notificationData(object $notifiable): array
    {
        $ref = $this->booking->booking_number ?? $this->booking->id;

        return [
            'title' => 'Payment Received',
            'message' => "Payment received for booking #{$ref}.",
            'url' => route('travel-agency.bookings.show', $this->booking->id),
            'booking_id' => $this->booking->id,
            'amount' => $this->booking->total_price,
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('travel-agency.'.$this->booking->package->travel_agency_id)];
    }
}
