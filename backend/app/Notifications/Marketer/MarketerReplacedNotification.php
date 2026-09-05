<?php

namespace App\Notifications\Marketer;

use App\Models\MarketerCampaignInvitation;
use Illuminate\Notifications\Notification;

class MarketerReplacedNotification extends Notification
{
    public function __construct(public readonly MarketerCampaignInvitation $oldInvitation) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type'        => 'marketer_replaced',
            'campaign_id' => $this->oldInvitation->campaign_id,
        ];
    }
}
