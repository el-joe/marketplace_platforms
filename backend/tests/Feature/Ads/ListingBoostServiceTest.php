<?php

namespace Tests\Feature\Ads;

use App\Models\PaidAdBooking;
use App\Models\PaidAdCreative;
use App\Models\PaidAdSlot;
use App\Models\VendorAdSubscription;
use App\Models\VendorListing;
use App\Services\Ads\ListingBoostService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

class ListingBoostServiceTest extends TestCase
{
    use RefreshDatabase;

    private function setUpBoth(Carbon $subEnds, Carbon $bookingEnds): VendorListing
    {
        $s = MarketplaceScenario::make()->build();
        $listing = $s->vendorListingFbp;
        $admin = \App\Models\Admin::factory()->create();

        $slot = PaidAdSlot::create([
            'country_id' => $s->country->id, 'created_by_admin_id' => $admin->id,
            'name' => 'Promo', 'slot_code' => 'p-'.Str::random(8), 'pricing_model' => 'fixed_daily',
            'base_rate' => 1000, 'currency' => 'AED', 'min_booking_days' => 1, 'max_booking_days' => 30,
            'is_available' => true, 'requires_approval' => false, 'max_concurrent' => 5,
            'lead_time_days' => 0, 'allowed_advertisers' => 'vendor', 'target_type' => 'listing_promotion',
        ]);
        $booking = PaidAdBooking::create([
            'booking_reference' => 'ADB-'.Str::random(6), 'paid_ad_slot_id' => $slot->id,
            'advertiser_type' => 'vendor', 'vendor_id' => $listing->vendor_id, 'country_id' => $s->country->id,
            'pricing_model' => 'fixed_daily', 'pricing_units' => 1, 'unit_rate' => 1000, 'agreed_rate' => 1000,
            'quoted_amount' => 1000, 'tax_amount' => 0, 'booked_from' => today(), 'booked_until' => $bookingEnds,
            'currency' => 'AED', 'status' => 'active', 'payment_status' => 'paid', 'payment_method' => 'wallet',
            'started_at' => now(),
        ]);
        PaidAdCreative::create([
            'paid_ad_booking_id' => $booking->id, 'version' => 1, 'vendor_id' => $listing->vendor_id,
            'destination_url' => '/p', 'destination_type' => 'listing', 'destination_reference_id' => $listing->id,
            'status' => 'approved', 'is_current' => true, 'approved_at' => now(),
        ]);
        $pkg = \App\Models\AdPackage::create(['tier' => 'serious', 'name_en' => 'P', 'name_ar' => 'P', 'price_monthly' => 100, 'currency' => 'AED']);
        VendorAdSubscription::create([
            'vendor_listing_id' => $listing->id, 'vendor_id' => $listing->vendor_id,
            'ad_package_id' => $pkg->id, 'status' => 'active',
            'starts_at' => now()->subDay(), 'ends_at' => $subEnds, 'amount_paid' => 0, 'currency' => 'AED',
        ]);

        return $listing;
    }

    public function test_cancelling_subscription_keeps_boost_from_active_booking(): void
    {
        $bookingEnd = today()->addDays(10);
        $listing = $this->setUpBoth(now()->addDays(3), $bookingEnd);
        $svc = app(ListingBoostService::class);
        $svc->refresh($listing);

        VendorAdSubscription::query()->update(['status' => 'cancelled']);
        $svc->refresh($listing);

        $listing->refresh();
        $this->assertTrue((bool) $listing->is_ad_boosted);
        $this->assertSame($bookingEnd->toDateString(), $listing->ad_boost_expires_at->toDateString());
    }

    public function test_expiry_of_one_source_keeps_the_other_expiry(): void
    {
        $listing = $this->setUpBoth(now()->addDays(3), today()->addDays(10));
        $svc = app(ListingBoostService::class);

        VendorAdSubscription::query()->update(['status' => 'expired']);
        $svc->refresh($listing);
        $this->assertTrue((bool) $listing->refresh()->is_ad_boosted);

        PaidAdBooking::query()->update(['status' => 'completed']);
        $svc->refresh($listing);
        $listing->refresh();
        $this->assertFalse((bool) $listing->is_ad_boosted);
        $this->assertNull($listing->ad_boost_expires_at);
    }
}
