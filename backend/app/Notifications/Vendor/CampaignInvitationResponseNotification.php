<?php

namespace App\Notifications\Vendor;

use App\Models\MarketerCampaignInvitation;
use App\Notifications\BaseDatabaseBroadcastNotification;

/**
 * Tells the vendor how a marketer answered an invitation (accepted / rejected / timed_out).
 * On reject / timeout it also says the platform is searching for a replacement.
 */
class CampaignInvitationResponseNotification extends BaseDatabaseBroadcastNotification
{
    public function __construct(
        private readonly MarketerCampaignInvitation $invitation,
        private readonly string $outcome, // accepted|rejected|timed_out
    ) {}

    public function notificationType(): string
    {
        return 'campaign_invitation_'.$this->outcome;
    }

    public function notificationData(object $notifiable): array
    {
        $campaign = $this->invitation->campaign;

        return [
            'title' => __('partner.marketer_campaigns.notify.'.$this->outcome.'_title'),
            'message' => __('partner.marketer_campaigns.notify.'.$this->outcome.'_message', [
                'marketer' => $this->invitation->marketer?->name,
                'campaign' => $campaign?->title,
            ]),
            'url' => route('partner.marketer-campaigns.show', $this->invitation->campaign_id),
            'campaign_id' => $this->invitation->campaign_id,
            'invitation_id' => $this->invitation->id,
            'outcome' => $this->outcome,
        ];
    }

    public function broadcastOn(): array
    {
        return [];
    }
}
