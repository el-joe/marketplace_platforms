<?php

namespace App\Notifications\Marketer;

use App\Models\MarketerCampaign;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;

class CampaignExpired extends BaseDatabaseBroadcastNotification
{
    public function __construct(
        public readonly MarketerCampaign $campaign,
        public readonly string $marketerAdminId,
    ) {}

    public function notificationType(): string
    {
        return 'campaign_expired';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title' => 'Campaign Ended',
            'message' => "Your campaign '{$this->campaign->name}' has ended.",
            'url' => route('marketer.campaigns.finished'),
            'campaign_id' => $this->campaign->id,
            'campaign_name' => $this->campaign->name,
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('marketer.'.$this->marketerAdminId)];
    }
}
