<?php

namespace App\Notifications\Vendor;

use App\Models\MarketerCampaign;
use App\Notifications\BaseDatabaseBroadcastNotification;

class CampaignAutoApprovedNotification extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly MarketerCampaign $campaign) {}

    public function notificationType(): string
    {
        return 'campaign_auto_approved';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title'          => 'تمت الموافقة التلقائية على حملتك',
            'message'        => 'تمت الموافقة التلقائية على حملتك بعد 36 ساعة.',
            'url'            => route('partner.marketer-campaigns.show', $this->campaign->id),
            'campaign_id'    => $this->campaign->id,
            'campaign_title' => $this->campaign->title,
        ];
    }

    public function broadcastOn(): array
    {
        return [];
    }
}
