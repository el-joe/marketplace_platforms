<?php

namespace App\Notifications\Vendor;

use App\Models\PaidAdBooking;
use App\Notifications\BaseDatabaseBroadcastNotification;

class AdSlotBookingApproved extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly PaidAdBooking $booking) {}

    public function notificationType(): string
    {
        return 'ad_slot_booking_approved';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title'      => 'Ad Slot Booking Approved',
            'message'    => "Your ad slot booking for \"{$this->booking->slot->name}\" has been approved and is now active.",
            'url'        => route('partner.ads.index'),
            'booking_id' => $this->booking->id,
        ];
    }

    public function broadcastOn(): array
    {
        return [];
    }
}
