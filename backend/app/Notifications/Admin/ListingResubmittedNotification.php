<?php

namespace App\Notifications\Admin;

use App\Models\VendorListing;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;

class ListingResubmittedNotification extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly VendorListing $listing) {}

    public function notificationType(): string
    {
        return 'listing_resubmitted';
    }

    public function notificationData(object $notifiable): array
    {
        $variant = $this->listing->productVariant;
        $product = $variant?->product;
        $name = $product ? ($product->name_ar ?: $product->name_en) : $this->listing->id;

        return [
            'title'      => 'Listing Resubmitted for Review',
            'message'    => "Listing \"{$name}\" was resubmitted after rejection and is awaiting review.",
            'listing_id' => $this->listing->id,
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
