<?php

namespace App\Notifications\Ads;

use App\Models\PaidAdCreative;
use App\Notifications\Ads\Concerns\ResolvesAdvertiserUrl;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;

class AdCreativeRejectedNotification extends BaseDatabaseBroadcastNotification
{
    use ResolvesAdvertiserUrl;

    public function __construct(
        private readonly PaidAdCreative $creative,
        private readonly string $reason,
        private readonly ?string $code,
    ) {}

    public function notificationType(): string
    {
        return 'ad_creative_rejected';
    }

    public function notificationData(object $notifiable): array
    {
        $booking = $this->creative->booking;

        return [
            'title' => __('notifications.ads.creative_rejected.title'),
            'message' => __('notifications.ads.creative_rejected.message', [
                'reference' => $booking->booking_reference,
                'reason' => $this->reason,
                'code' => $this->code ?? __('notifications.ads.creative_rejected.no_code'),
            ]),
            'url' => $this->advertiserUrl($booking),
            'booking_id' => $booking->id,
            'creative_id' => $this->creative->id,
            'reference' => $booking->booking_reference,
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
            'data' => ['screen' => 'ad_booking_detail', 'id' => $this->creative->paid_ad_booking_id, 'type' => class_basename(static::class)],
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
