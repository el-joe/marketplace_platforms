<?php

namespace App\Notifications\Vendor;

use App\Models\PaidAdBooking;
use App\Notifications\BaseDatabaseBroadcastNotification;

class AdSlotBookingRejected extends BaseDatabaseBroadcastNotification
{
    public function __construct(
        private readonly PaidAdBooking $booking,
        private readonly string $reason = ''
    ) {}

    public function notificationType(): string
    {
        return 'ad_slot_booking_rejected';
    }

    public function notificationData(object $notifiable): array
    {
        $message = "Your ad slot booking for \"{$this->booking->slot->name}\" has been rejected.";
        if ($this->reason) {
            $message .= " Reason: {$this->reason}";
        }

        return [
            'title'      => 'Ad Slot Booking Rejected',
            'message'    => $message,
            'url'        => route('partner.ads.index'),
            'booking_id' => $this->booking->id,
        ];
    }

    public function broadcastOn(): array
    {
        return [];
    }
}
