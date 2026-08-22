<?php

namespace App\Notifications\Vendor;

use App\Models\ClassifiedListing;
use App\Notifications\BaseDatabaseBroadcastNotification;

class ClassifiedListingRejected extends BaseDatabaseBroadcastNotification
{
    public function __construct(
        private readonly ClassifiedListing $listing,
        private readonly string $reason = ''
    ) {}

    public function notificationType(): string
    {
        return 'classified_listing_rejected';
    }

    public function notificationData(object $notifiable): array
    {
        $message = "Your listing \"{$this->listing->title_en}\" has been rejected.";
        if ($this->reason) {
            $message .= " Reason: {$this->reason}";
        }

        return [
            'title'      => 'Classified Listing Rejected',
            'message'    => $message,
            'url'        => route('partner.classifieds.show', $this->listing->id),
            'listing_id' => $this->listing->id,
        ];
    }

    public function broadcastOn(): array
    {
        return [];
    }
}
