<?php

namespace App\Notifications\Admin;

use App\Models\TravelAgencyCampaignOffer;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;

class TravelAgencyCampaignOfferSubmitted extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly TravelAgencyCampaignOffer $offer) {}

    public function notificationType(): string
    {
        return 'travel_agency_campaign_offer_submitted';
    }

    public function notificationData(object $notifiable): array
    {
        $agencyName    = $this->offer->agency->name;
        $packagesCount = $this->offer->packages()->count();
        $commission    = number_format($this->offer->offered_commission_rate, 0);

        return [
            'title'          => 'Campaign Offer Pending Review',
            'message'        => "{$agencyName} submitted a campaign offer '{$this->offer->name}' for review — {$packagesCount} packages, {$commission}% commission offered to marketers.",
            'url'            => route('admin.dashboard'),
            'offer_id'       => $this->offer->id,
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
