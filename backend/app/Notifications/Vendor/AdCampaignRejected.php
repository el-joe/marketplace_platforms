<?php

namespace App\Notifications\Vendor;

use App\Models\AdCampaign;
use App\Notifications\BaseDatabaseBroadcastNotification;

class AdCampaignRejected extends BaseDatabaseBroadcastNotification
{
    public function __construct(
        private readonly AdCampaign $campaign,
        private readonly string $reason = ''
    ) {}

    public function notificationType(): string
    {
        return 'ad_campaign_rejected';
    }

    public function notificationData(object $notifiable): array
    {
        $message = "Your ad campaign \"{$this->campaign->name}\" has been rejected.";
        if ($this->reason) {
            $message .= " Reason: {$this->reason}";
        }

        return [
            'title'       => 'Ad Campaign Rejected',
            'message'     => $message,
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
