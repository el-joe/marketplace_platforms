<?php

namespace App\Notifications\Admin;

use App\Models\VendorListing;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;

/**
 * Client feature request doc, section 6: fired by
 * App\Console\Commands\FlagOverstoredUnpaidProducts when a listing has been
 * in storage over a year with unpaid storage fees exceeding its locked-in
 * first price, and has just been flagged disposable_by_admin.
 */
class OverstoredUnpaidProductFlagged extends BaseDatabaseBroadcastNotification
{
    public function __construct(
        private readonly VendorListing $listing,
        private readonly int $unpaidTotal,
    ) {}

    public function notificationType(): string
    {
        return 'overstored_unpaid_product_flagged';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title' => 'Listing Flagged Disposable',
            'message' => "Vendor listing #{$this->listing->id} has unpaid storage fees ({$this->unpaidTotal} {$this->listing->currency}) "
                ."exceeding its first price ({$this->listing->first_price} {$this->listing->currency}) after over a year in storage.",
            'vendor_listing_id' => $this->listing->id,
            'link' => route('admin.vendor-listings.show', $this->listing->id),
        ];
    }

    public function broadcastOn(mixed $notifiable = null): array
    {
        if (! $notifiable) {
            return [];
        }

        return [new PrivateChannel('admin.'.$notifiable->id)];
    }
}
