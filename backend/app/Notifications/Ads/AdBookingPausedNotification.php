<?php

namespace App\Notifications\Ads;

use App\Models\PaidAdBooking;
use App\Notifications\Ads\Concerns\ResolvesAdvertiserUrl;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;

class AdBookingPausedNotification extends BaseDatabaseBroadcastNotification
{
    use ResolvesAdvertiserUrl;

    public function __construct(
        private readonly PaidAdBooking $booking,
        private readonly string $reason,
    ) {}

    public function notificationType(): string
    {
        return 'ad_booking_paused';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title' => __('notifications.ads.booking_paused.title'),
            'message' => __('notifications.ads.booking_paused.message', [
                'reference' => $this->booking->booking_reference,
                'reason' => $this->reason,
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
