<?php

namespace App\Notifications\Customer;

use App\Models\TravelBooking;
use App\Support\StorefrontUrl;
use Illuminate\Broadcasting\PrivateChannel;

class TravelBookingConfirmed extends BaseCustomerNotification
{
    public function __construct(private readonly TravelBooking $booking) {}

    public function notificationType(): string
    {
        return 'travel_booking_confirmed';
    }

    public function notificationData(object $notifiable): array
    {
        $this->booking->loadMissing('package:id,title');

        return [
            'title'          => 'Booking Received',
            'title_ar'       => 'تم استلام الحجز',
            'message'        => "Your travel booking #{$this->booking->booking_number} for \"{$this->booking->package->title}\" has been received and is pending documents.",
            'message_ar'     => "تم استلام حجز السفر رقم #{$this->booking->booking_number} الخاص بـ \"{$this->booking->package->title}\" وهو بانتظار المستندات.",
            'url'            => StorefrontUrl::to($notifiable->country, 'my-bookings/'.$this->booking->id, StorefrontUrl::languageOf($notifiable)),
            'booking_id'     => $this->booking->id,
            'booking_number' => $this->booking->booking_number,
            'package_title'  => $this->booking->package->title,
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('customer.' . $this->booking->customer_id)];
    }
}
