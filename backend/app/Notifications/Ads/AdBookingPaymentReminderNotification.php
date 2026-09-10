<?php

namespace App\Notifications\Ads;

use App\Models\PaidAdBooking;
use App\Notifications\Ads\Concerns\ResolvesAdvertiserUrl;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;

/** Sent 24h and 2h before payment_due_at by the scheduler. */
class AdBookingPaymentReminderNotification extends BaseDatabaseBroadcastNotification
{
    use ResolvesAdvertiserUrl;

    /** @param string $window one of: 24h, 2h */
    public function __construct(
        private readonly PaidAdBooking $booking,
        private readonly string $window,
    ) {}

    public function notificationType(): string
    {
        return 'ad_booking_payment_reminder';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title' => __('notifications.ads.payment_reminder.title'),
            'message' => __('notifications.ads.payment_reminder.message', [
                'reference' => $this->booking->booking_reference,
                'payment_due_at' => optional($this->booking->payment_due_at)->translatedFormat('d M Y H:i'),
            ]),
            'url' => $this->advertiserUrl($this->booking),
            'booking_id' => $this->booking->id,
            'reference' => $this->booking->booking_reference,
            'window' => $this->window,
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
