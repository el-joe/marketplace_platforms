<?php

namespace App\Notifications\Admin;

use App\Models\AdCampaign;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;

class AdCampaignSubmittedForReview extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly AdCampaign $campaign) {}

    public function notificationType(): string
    {
        return 'ad_campaign_submitted_for_review';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title'       => 'Ad Campaign Pending Review',
            'message'     => "Vendor ad campaign \"{$this->campaign->name}\" has been submitted and requires review.",
            'campaign_id' => $this->campaign->id,
            'link'        => route('admin.ad-campaigns.show', $this->campaign->id),
        ];
    }

    public function broadcastOn(mixed $notifiable = null): array
    {
        if (! $notifiable) {
            return [];
        }

        return [new PrivateChannel('admin.' . $notifiable->id)];
    }
}
