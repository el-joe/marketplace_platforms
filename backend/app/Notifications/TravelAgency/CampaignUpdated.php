<?php

namespace App\Notifications\TravelAgency;

use App\Models\MarketerCampaign;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;

/**
 * A single notice for every lifecycle event of a trip campaign an agency owns (went live, rejected, marketer
 * replaced, done...). The event key selects the wording from `travel.campaigns.notifications.*`.
 */
class CampaignUpdated extends BaseDatabaseBroadcastNotification
{
    public function __construct(
        private readonly MarketerCampaign $campaign,
        private readonly string $event,
    ) {}

    public function notificationType(): string
    {
        return 'travel_campaign_updated';
    }

    public function notificationData(object $notifiable): array
    {
        $key = 'travel.campaigns.notifications.'.$this->event;
        $title = $this->campaign->travelPackage?->title_en ?? '';

        return [
            'title' => __('travel.campaigns.notifications.title'),
            'message' => __(
                \Illuminate\Support\Facades\Lang::has($key) ? $key : 'travel.campaigns.notifications.generic',
                ['trip' => $title]
            ),
            'url' => route('travel-agency.campaigns.show', $this->campaign->id),
            'campaign_id' => $this->campaign->id,
            'event' => $this->event,
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('travel-agency.'.$this->campaign->owner_id)];
    }
}
