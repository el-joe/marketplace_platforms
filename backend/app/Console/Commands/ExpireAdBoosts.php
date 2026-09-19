<?php

namespace App\Console\Commands;

use App\Models\VendorAdSubscription;
use App\Services\Ads\ListingBoostService;
use App\Models\VendorListing;
use Illuminate\Console\Command;

class ExpireAdBoosts extends Command
{
    protected $signature = 'ads:expire-boosts';

    protected $description = 'Expire Nawi Ads subscriptions past their end date and clear the listing boost';

    public function handle(): int
    {
        $expired = VendorAdSubscription::where('status', 'active')
            ->where('ends_at', '<=', now())
            ->get();

        foreach ($expired as $subscription) {
            $subscription->update(['status' => 'expired']);

            $listing = VendorListing::find($subscription->vendor_listing_id);
            if ($listing) {
                app(ListingBoostService::class)->refresh($listing);
            }
        }

        $this->info("Expired {$expired->count()} ad subscription(s).");

        return self::SUCCESS;
    }
}
