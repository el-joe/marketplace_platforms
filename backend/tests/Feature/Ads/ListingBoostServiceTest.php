<?php

namespace Tests\Feature\Ads;

use App\Models\PaidAdBooking;
use App\Models\PaidAdCreative;
use App\Models\PaidAdSlot;
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

    private function setUpSlot($s, $admin, bool $popup = false): PaidAdSlot
    {
        return PaidAdSlot::create([
            'country_id' => $s->country->id, 'created_by_admin_id' => $admin->id,
            'name' => 'Promo', 'slot_code' => 'p-'.Str::random(8), 'pricing_model' => 'fixed_daily',
            'base_rate' => 1000, 'currency' => 'AED', 'min_booking_days' => 1, 'max_booking_days' => 30,
            'is_available' => true, 'requires_approval' => false, 'max_concurrent' => 5,
            'lead_time_days' => 0, 'allowed_advertisers' => 'vendor', 'target_type' => 'listing_promotion',
            'shows_popup' => $popup,
        ]);
    }

    private function book($s, PaidAdSlot $slot, VendorListing $listing, Carbon $ends): PaidAdBooking
    {
        $booking = PaidAdBooking::create([
            'booking_reference' => 'ADB-'.Str::random(6), 'paid_ad_slot_id' => $slot->id,
            'advertiser_type' => 'vendor', 'vendor_id' => $listing->vendor_id, 'country_id' => $s->country->id,
            'pricing_model' => 'fixed_daily', 'pricing_units' => 1, 'unit_rate' => 1000, 'agreed_rate' => 1000,
            'quoted_amount' => 1000, 'tax_amount' => 0, 'booked_from' => today(), 'booked_until' => $ends,
            'currency' => 'AED', 'status' => 'active', 'payment_status' => 'paid', 'payment_method' => 'wallet',
            'started_at' => now(),
        ]);
        PaidAdCreative::create([
            'paid_ad_booking_id' => $booking->id, 'version' => 1, 'vendor_id' => $listing->vendor_id,
            'destination_url' => '/p', 'destination_type' => 'listing', 'destination_reference_id' => $listing->id,
            'status' => 'approved', 'is_current' => true, 'approved_at' => now(),
        ]);

        return $booking;
    }

    private function setUpTwo(Carbon $end1, Carbon $end2): array
    {
        $s = MarketplaceScenario::make()->build();
        $listing = $s->vendorListingFbp;
        $slot = $this->setUpSlot($s, \App\Models\Admin::factory()->create());

        return [$listing, $this->book($s, $slot, $listing, $end1), $this->book($s, $slot, $listing, $end2)];
    }

    public function test_cancelling_one_of_two_overlapping_bookings_keeps_boost_and_later_expiry(): void
    {
        $short = today()->addDays(3);
        $long = today()->addDays(10);
        [$listing, $b1, $b2] = $this->setUpTwo($short, $long);
        $svc = app(ListingBoostService::class);
        $svc->refresh($listing);
        $this->assertSame($long->toDateString(), $listing->refresh()->ad_boost_expires_at->toDateString());

        $b2->update(['status' => 'cancelled']);
        $svc->refresh($listing);

        $listing->refresh();
        $this->assertTrue((bool) $listing->is_ad_boosted);
        $this->assertSame($short->toDateString(), $listing->ad_boost_expires_at->toDateString());
    }

    public function test_boost_clears_when_all_bookings_end(): void
    {
        [$listing, $b1, $b2] = $this->setUpTwo(today()->addDays(3), today()->addDays(10));
        $svc = app(ListingBoostService::class);

        $b1->update(['status' => 'cancelled']);
        $svc->refresh($listing);
        $this->assertTrue((bool) $listing->refresh()->is_ad_boosted);

        $b2->update(['status' => 'completed']);
        $svc->refresh($listing);
        $listing->refresh();
        $this->assertFalse((bool) $listing->is_ad_boosted);
        $this->assertNull($listing->ad_boost_expires_at);
    }
}
