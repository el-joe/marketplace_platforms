<?php

namespace App\Notifications\Marketer;

use App\Models\MarketerCampaignInvitation;
use Illuminate\Notifications\Notification;

class CampaignInvitationReceivedNotification extends Notification
{
    public function __construct(public readonly MarketerCampaignInvitation $invitation) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type'          => 'campaign_invitation_received',
            'invitation_id' => $this->invitation->id,
            'campaign_id'   => $this->invitation->campaign_id,
        ];
    }
}
