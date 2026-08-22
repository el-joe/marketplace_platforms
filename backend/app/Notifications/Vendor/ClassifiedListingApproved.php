<?php

namespace App\Notifications\Vendor;

use App\Models\ClassifiedListing;
use App\Notifications\BaseDatabaseBroadcastNotification;

class ClassifiedListingApproved extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly ClassifiedListing $listing) {}

    public function notificationType(): string
    {
        return 'classified_listing_approved';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title'      => 'Classified Listing Approved',
            'message'    => "Your listing \"{$this->listing->title_en}\" has been approved and is now live.",
            'url'        => route('partner.classifieds.show', $this->listing->id),
            'listing_id' => $this->listing->id,
        ];
    }

    public function broadcastOn(): array
    {
        return [];
    }
}
