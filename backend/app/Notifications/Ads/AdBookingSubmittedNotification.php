<?php

namespace App\Notifications\Ads;

use App\Models\PaidAdBooking;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;

/** Sent to admins with ad_bookings.review when a booking is submitted for review. */
class AdBookingSubmittedNotification extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly PaidAdBooking $booking) {}

    public function notificationType(): string
    {
        return 'ad_booking_submitted';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title' => __('notifications.ads.booking_submitted.title'),
            'message' => __('notifications.ads.booking_submitted.message', [
                'reference' => $this->booking->booking_reference,
                'advertiser' => $this->booking->advertiserName(),
            ]),
            'url' => route('admin.paid-ad-bookings.show', $this->booking->id),
            'booking_id' => $this->booking->id,
            'reference' => $this->booking->booking_reference,
        ];
    }

    public function broadcastOn(mixed $notifiable = null): array
    {
        if (! $notifiable) {
            return [];
        }

        return [new PrivateChannel('admin.' . $notifiable->id)];
    }
}
