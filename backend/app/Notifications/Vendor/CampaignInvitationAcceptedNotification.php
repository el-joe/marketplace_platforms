<?php

namespace App\Notifications\Vendor;

use App\Models\MarketerCampaignInvitation;
use App\Notifications\BaseDatabaseBroadcastNotification;

class CampaignInvitationAcceptedNotification extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly MarketerCampaignInvitation $invitation) {}

    public function notificationType(): string
    {
        return 'campaign_invitation_accepted';
    }

    public function notificationData(object $notifiable): array
    {
        $marketerName = $this->invitation->marketer?->store_name;

        return [
            'title'          => 'قبول دعوة الحملة',
            'message'        => "قبل الماركتر {$marketerName} دعوة حملتك.",
            'url'            => route('partner.marketer-campaigns.show', $this->invitation->campaign_id),
            'campaign_id'    => $this->invitation->campaign_id,
            'invitation_id'  => $this->invitation->id,
            'marketer_name'  => $marketerName,
            'marketer_type'  => $this->invitation->marketer?->marketer_type,
        ];
    }

    public function broadcastOn(): array
    {
        return [];
    }
}
