<?php

namespace App\Notifications\Vendor;

use App\Models\MarketerCampaignInvitation;
use App\Notifications\BaseDatabaseBroadcastNotification;

class CampaignInvitationRejectedNotification extends BaseDatabaseBroadcastNotification
{
    public function __construct(
        private readonly MarketerCampaignInvitation $invitation,
        private readonly bool $replacementSearching = true,
    ) {}

    public function notificationType(): string
    {
        return 'campaign_invitation_rejected';
    }

    public function notificationData(object $notifiable): array
    {
        $marketerName = $this->invitation->marketer?->store_name;

        return [
            'title'                  => 'رفض دعوة الحملة',
            'message'                => "رفض الماركتر {$marketerName} الدعوة. جارٍ البحث عن بديل.",
            'url'                    => route('partner.marketer-campaigns.show', $this->invitation->campaign_id),
            'campaign_id'            => $this->invitation->campaign_id,
            'invitation_id'          => $this->invitation->id,
            'marketer_name'          => $marketerName,
            'replacement_searching'  => $this->replacementSearching,
        ];
    }

    public function broadcastOn(): array
    {
        return [];
    }
}
