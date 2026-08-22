<?php

namespace App\Notifications\Vendor;

use App\Models\MarketerCampaign;
use App\Notifications\BaseDatabaseBroadcastNotification;

class CampaignRejectedNotification extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly MarketerCampaign $campaign) {}

    public function notificationType(): string
    {
        return 'campaign_rejected';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title'             => 'تم رفض حملتك',
            'message'           => "تم رفض حملتك. السبب: {$this->campaign->rejection_reason}",
            'url'               => route('partner.marketer-campaigns.show', $this->campaign->id),
            'campaign_id'       => $this->campaign->id,
            'rejection_reason'  => $this->campaign->rejection_reason,
        ];
    }

    public function broadcastOn(): array
    {
        return [];
    }
}
