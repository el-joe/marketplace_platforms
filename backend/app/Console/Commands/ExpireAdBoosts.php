<?php

namespace App\Console\Commands;

use App\Models\VendorAdSubscription;
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

            VendorListing::where('id', $subscription->vendor_listing_id)->update([
                'is_ad_boosted' => false,
                'ad_boost_expires_at' => null,
            ]);
        }

        $this->info("Expired {$expired->count()} ad subscription(s).");

        return self::SUCCESS;
    }
}
