<?php

namespace App\Notifications\Vendor;

use App\Models\MarketerCampaign;
use App\Notifications\BaseDatabaseBroadcastNotification;

class CampaignApprovedNotification extends BaseDatabaseBroadcastNotification
{
    public function __construct(
        private readonly MarketerCampaign $campaign,
        private readonly int $invitedCount,
    ) {}

    public function notificationType(): string
    {
        return 'campaign_approved';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title'                 => 'تم قبول حملتك',
            'message'               => 'تم قبول حملتك! الماركترز سيتلقون الدعوات الآن.',
            'url'                   => route('partner.marketer-campaigns.show', $this->campaign->id),
            'campaign_id'           => $this->campaign->id,
            'platform_commission'   => $this->campaign->platform_commission_amount,
            'marketer_commission'   => $this->campaign->marketer_commission_amount,
            'invited_count'         => $this->invitedCount,
        ];
    }

    public function broadcastOn(): array
    {
        return [];
    }
}
