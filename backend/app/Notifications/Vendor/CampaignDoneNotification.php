<?php

namespace App\Notifications\Vendor;

use App\Models\MarketerCampaign;
use App\Notifications\BaseDatabaseBroadcastNotification;

class CampaignDoneNotification extends BaseDatabaseBroadcastNotification
{
    public function __construct(
        private readonly MarketerCampaign $campaign,
        private readonly int $totalConversions,
        private readonly float $totalCommissionEarned,
    ) {}

    public function notificationType(): string
    {
        return 'campaign_done';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title'                     => 'انتهت الحملة',
            'message'                   => 'انتهت الحملة بسبب نفاد المخزون.',
            'url'                       => route('partner.marketer-campaigns.show', $this->campaign->id),
            'campaign_id'               => $this->campaign->id,
            'total_conversions'         => $this->totalConversions,
            'total_commission_earned'   => $this->totalCommissionEarned,
        ];
    }

    public function broadcastOn(): array
    {
        return [];
    }
}
