<?php

namespace App\Notifications\Ads;

use App\Models\PaidAdBooking;
use App\Notifications\Ads\Concerns\ResolvesAdvertiserUrl;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;

/**
 * Sent to advertiser admins when a booking is approved.
 * The message varies depending on what happens immediately after approval:
 * scheduled for a future start date, live now, or awaiting payment.
 */
class AdBookingApprovedNotification extends BaseDatabaseBroadcastNotification
{
    use ResolvesAdvertiserUrl;

    /** @param string $outcome one of: scheduled, live, payment_due */
    public function __construct(
        private readonly PaidAdBooking $booking,
        private readonly string $outcome,
    ) {}

    public function notificationType(): string
    {
        return 'ad_booking_approved';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title' => __('notifications.ads.booking_approved.title'),
            'message' => __("notifications.ads.booking_approved.{$this->outcome}", [
                'reference' => $this->booking->booking_reference,
                'date' => optional($this->booking->booked_from)->translatedFormat('d M Y'),
                'payment_due_at' => optional($this->booking->payment_due_at)->translatedFormat('d M Y H:i'),
            ]),
            'url' => $this->advertiserUrl($this->booking),
            'booking_id' => $this->booking->id,
            'reference' => $this->booking->booking_reference,
        ];
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'push'];
    }

    public function toPush(object $notifiable): array
    {
        $data = $this->notificationData($notifiable);

        return [
            'title' => $data['title'],
            'body' => $data['message'],
            'data' => ['screen' => 'ad_booking_detail', 'id' => $this->booking->id, 'type' => class_basename(static::class)],
        ];
    }

    public function broadcastOn(mixed $notifiable = null): array
    {
        if (! $notifiable) {
            return [];
        }

        return [new PrivateChannel('vendor_or_marketer_admin.' . $notifiable->id)];
    }
}
