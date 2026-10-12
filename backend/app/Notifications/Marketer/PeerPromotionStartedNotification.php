<?php

namespace App\Notifications\Marketer;

use App\Models\MarketerCampaign;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;

/**
 * P13: tells an influencer that another influencer started promoting his product.
 */
class PeerPromotionStartedNotification extends BaseDatabaseBroadcastNotification
{
    public function __construct(
        public readonly MarketerCampaign $campaign,
        public readonly string $marketerAdminId,
    ) {}

    public function notificationType(): string
    {
        return 'peer_promotion_started';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'campaign_id' => $this->campaign->id,
            'title' => __('marketer.campaigns.peer.notification_title'),
            'message' => __('marketer.campaigns.peer.notification_message', [
                'promoter' => $this->campaign->promoter?->name ?? '—',
                'product' => $this->campaign->getPromotedTitle(),
            ]),
            'url' => route('marketer.campaigns.promoted-by-others'),
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('marketer.'.$this->marketerAdminId)];
    }
}
