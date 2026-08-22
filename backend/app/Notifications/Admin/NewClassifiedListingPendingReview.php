<?php

namespace App\Notifications\Admin;

use App\Models\ClassifiedListing;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;

class NewClassifiedListingPendingReview extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly ClassifiedListing $listing) {}

    public function notificationType(): string
    {
        return 'new_classified_listing_pending_review';
    }

    public function notificationData(object $notifiable): array
    {
        $title = $this->listing->title_en ?: $this->listing->title_ar;

        return [
            'title'      => 'Classified Listing Pending Review',
            'message'    => "Listing \"{$title}\" has been submitted and is awaiting review.",
            'listing_id' => $this->listing->id,
            'link'       => route('admin.classifieds.listings.show', $this->listing->id),
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
