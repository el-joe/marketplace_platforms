<?php

namespace App\Notifications\Vendor;

use App\Models\AdCampaign;
use App\Notifications\BaseDatabaseBroadcastNotification;

class AdCampaignApproved extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly AdCampaign $campaign) {}

    public function notificationType(): string
    {
        return 'ad_campaign_approved';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title'       => 'Ad Campaign Approved',
            'message'     => "Your ad campaign \"{$this->campaign->name}\" has been approved and is now live.",
            'url'         => route('partner.ads.show', $this->campaign->id),
            'campaign_id' => $this->campaign->id,
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
            'body'  => $data['message'],
            'data'  => [
                'screen' => 'dashboard',
                'id'     => null,
                'type'   => class_basename(static::class),
            ],
        ];
    }

    public function broadcastOn(): array
    {
        return [];
    }
}
