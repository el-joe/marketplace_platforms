<?php

namespace Tests\Feature\Ads;

use App\Models\Admin;
use App\Models\PaidAdBooking;
use App\Models\PaidAdCreative;
use App\Models\PaidAdSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

class AdPopupControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeBooking(bool $popup, string $url): PaidAdBooking
    {
        $s = MarketplaceScenario::make()->build();
        $listing = $s->vendorListingFbp;
        $admin = Admin::factory()->create();
        $slot = PaidAdSlot::create([
            'country_id' => $s->country->id, 'created_by_admin_id' => $admin->id,
            'name' => 'Promo', 'slot_code' => 'p-'.Str::random(8), 'pricing_model' => 'fixed_daily',
            'base_rate' => 1000, 'currency' => 'AED', 'min_booking_days' => 1, 'max_booking_days' => 30,
            'is_available' => true, 'requires_approval' => false, 'max_concurrent' => 5,
            'lead_time_days' => 0, 'allowed_advertisers' => 'vendor', 'target_type' => 'listing_promotion',
            'shows_popup' => $popup,
        ]);
        $booking = PaidAdBooking::create([
            'booking_reference' => 'ADB-'.Str::random(6), 'paid_ad_slot_id' => $slot->id,
            'advertiser_type' => 'vendor', 'vendor_id' => $listing->vendor_id, 'country_id' => $s->country->id,
            'pricing_model' => 'fixed_daily', 'pricing_units' => 1, 'unit_rate' => 1000, 'agreed_rate' => 1000,
            'quoted_amount' => 1000, 'tax_amount' => 0, 'booked_from' => today(), 'booked_until' => today()->addDays(5),
            'currency' => 'AED', 'status' => 'active', 'payment_status' => 'paid', 'payment_method' => 'wallet',
            'started_at' => now(),
        ]);
        PaidAdCreative::create([
            'paid_ad_booking_id' => $booking->id, 'version' => 1, 'vendor_id' => $listing->vendor_id,
            'title_en' => 'Big deal', 'subtitle_en' => 'Body', 'destination_url' => $url,
            'destination_type' => 'listing', 'destination_reference_id' => $listing->id,
            'status' => 'approved', 'is_current' => true, 'approved_at' => now(),
        ]);

        return $booking;
    }

    public function test_no_active_returns_null(): void
    {
        $this->getJson($this->popupUrl())->assertOk()->assertJson(['popup' => null]);
    }

    public function test_slot_booking_popup_is_served(): void
    {
        $b = $this->makeBooking(true, 'https://example.com/x');
        $this->getJson($this->popupUrl())->assertOk()
            ->assertJsonPath('popup.id', $b->id)
            ->assertJsonPath('popup.title_en', 'Big deal')
            ->assertJsonPath('popup.cta_url', 'https://example.com/x');
    }

    public function test_bad_cta_scheme_is_dropped_and_non_popup_slot_ignored(): void
    {
        $this->makeBooking(true, 'javascript:alert(1)');
        $this->getJson($this->popupUrl())->assertOk()->assertJsonPath('popup.cta_url', null);
    }

    public function test_slot_without_shows_popup_is_ignored(): void
    {
        $this->makeBooking(false, 'https://example.com');
        $this->getJson($this->popupUrl())->assertOk()->assertJson(['popup' => null]);
    }

    public function test_popup_is_scoped_to_the_requested_country(): void
    {
        $b = $this->makeBooking(true, 'https://example.com/x');
        $other = \App\Models\Country::factory()->create(['is_active' => true]);

        $this->getJson($this->popupUrl($b->country))->assertOk()->assertJsonPath('popup.id', $b->id);
        $this->getJson($this->popupUrl($other))->assertOk()->assertJson(['popup' => null]);
        $this->getJson('/api/public/v1/zz-nope/active-popup')->assertNotFound();
    }

    private function popupUrl(?\App\Models\Country $country = null): string
    {
        $country ??= \App\Models\Country::query()->first() ?? MarketplaceScenario::make()->build()->country;
        if (! $country->site_code) {
            $country->update(['site_code' => 'c'.strtolower(Str::random(5))]);
        }

        return "/api/public/v1/{$country->site_code}/active-popup";
    }
}
