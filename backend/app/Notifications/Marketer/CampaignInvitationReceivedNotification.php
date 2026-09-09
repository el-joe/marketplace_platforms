<?php

namespace App\Notifications\Marketer;

use App\Models\MarketerCampaignInvitation;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;

class CampaignInvitationReceivedNotification extends BaseDatabaseBroadcastNotification
{
    public function __construct(
        public readonly MarketerCampaignInvitation $invitation,
        public readonly string $marketerAdminId,
    ) {}

    public function notificationType(): string
    {
        return 'campaign_invitation_received';
    }

    public function notificationData(object $notifiable): array
    {
        $campaign = $this->invitation->campaign;

        return [
            'invitation_id' => $this->invitation->id,
            'campaign_id'   => $this->invitation->campaign_id,
            'title'         => 'دعوة حملة جديدة',
            'message'       => 'تمت دعوتك للترويج لـ "' . $campaign?->getPromotedTitle() . '".',
            'url'           => route('marketer.invitations.index'),
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('marketer.' . $this->marketerAdminId)];
    }
}
