<?php

namespace Tests\Feature;

use App\Events\ListingStockChanged;
use App\Support\ListingCacheVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

class ListingCacheVersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_listing_update_and_delete_bump_cache_version(): void
    {
        $scenario = MarketplaceScenario::make()->build();

        $before = ListingCacheVersion::current();
        $scenario->vendorListingFbp->update(['price' => 4321]);
        $afterUpdate = ListingCacheVersion::current();
        $this->assertGreaterThan($before, $afterUpdate);

        $scenario->vendorListingFbp->delete();
        $this->assertGreaterThan($afterUpdate, ListingCacheVersion::current());
    }

    public function test_stock_changed_event_bumps_version_and_resyncs_buybox_stock(): void
    {
        $scenario = MarketplaceScenario::make()->build();

        $before = ListingCacheVersion::current();
        event(new ListingStockChanged($scenario->vendorListingFbp->id, null));

        $this->assertGreaterThan($before, ListingCacheVersion::current());
        $this->assertGreaterThan(0, (int) DB::table('product_country_buybox')
            ->where('product_id', $scenario->product->id)->value('total_stock'));
    }
}
