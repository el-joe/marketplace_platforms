<?php

namespace App\Notifications\Marketer;

use App\Models\MarketerCampaignInvitation;
use Illuminate\Notifications\Notification;

class CampaignInvitationAcceptedNotification extends Notification
{
    public function __construct(public readonly MarketerCampaignInvitation $invitation) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type'          => 'campaign_invitation_accepted',
            'invitation_id' => $this->invitation->id,
        ];
    }
}
