<?php

namespace Tests\Feature\Ads;

use App\Models\Admin;
use App\Models\PaidAdBooking;
use App\Models\PaidAdCreative;
use App\Models\PaidAdSlot;
use App\Models\Wallet;
use App\Services\Ads\AdBookingService;
use App\Services\Ads\AdSlotQuoteService;
use Database\Seeders\NawiAdsSlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MarketplaceScenario;
use App\Models\Country;
use Tests\TestCase;

class NawiAdsLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function setUpSeeded(string $slotCode): array
    {
        $s = MarketplaceScenario::make()->build();
        Admin::factory()->create();
        $this->seed(NawiAdsSlotSeeder::class);

        $slot = PaidAdSlot::where('slot_code', 'like', $slotCode.'-%')
            ->where('country_id', $s->vendorListingFbp->vendor->country_id)->firstOrFail();
        $slot->update(['allowed_advertisers' => 'vendor']);
        $slot->refresh();

        $listing = $s->vendorListingFbp;
        $vendor = $listing->vendor;
        Wallet::create(['owner_type' => 'vendor', 'owner_id' => $vendor->id, 'currency' => $slot->currency,
            'balance' => 100000000, 'pending_balance' => 0]);

        return [$slot, $vendor, $listing];
    }

    private function draftWithCreative(PaidAdSlot $slot, $vendor, $listing, string $status, array $creative = []): PaidAdBooking
    {
        // Seeded slots carry the default 1-day lead time, so booking starts tomorrow.
        $from = today()->addDay();
        $to = $from->copy()->addDays($slot->min_booking_days - 1);
        $b = app(AdBookingService::class)->createDraft($slot, $vendor, $from, $to, null, 'wallet');
        PaidAdCreative::create(array_merge([
            'paid_ad_booking_id' => $b->id, 'version' => 1, 'vendor_id' => $vendor->id,
            'title_en' => 'Hello', 'destination_url' => 'https://example.com/p',
            'destination_type' => 'listing', 'destination_reference_id' => $listing->id,
            'status' => $status, 'is_current' => true,
            'approved_at' => $status === 'approved' ? now() : null,
        ], $creative));

        return $b;
    }

    public function test_seeder_is_idempotent_and_registered(): void
    {
        MarketplaceScenario::make()->build();
        Admin::factory()->create();
        $this->seed(NawiAdsSlotSeeder::class);
        $this->seed(NawiAdsSlotSeeder::class);
        $this->assertSame(Country::count() * 2, PaidAdSlot::where('slot_code', 'like', 'listing-boost-%')->count());

        $src = file_get_contents(database_path('seeders/DatabaseSeeder.php'));
        $this->assertStringContainsString('NawiAdsSlotSeeder::class', $src);
    }

    public function test_serious_tier_full_lifecycle_boosts_then_clears(): void
    {
        [$slot, $vendor, $listing] = $this->setUpSeeded('listing-boost-serious');
        $svc = app(AdBookingService::class);

        $quote = app(AdSlotQuoteService::class)->quote($slot, today()->addDay(), today()->addDays(30));
        $this->assertSame(500, $quote['subtotal']);

        $b = $this->draftWithCreative($slot, $vendor, $listing, 'approved');
        $svc->submit($b); // no approval required: auto approve -> wallet pay -> active

        $b->refresh();
        $this->assertSame('scheduled', $b->status->value);
        $this->assertSame('paid', $b->payment_status->value);
        $this->assertFalse((bool) $listing->fresh()->is_ad_boosted);

        $this->runSchedulerAt(today()->addDay()->setTime(12, 0));
        $this->assertSame('active', $b->fresh()->status->value);

        $listing->refresh();
        $this->assertTrue((bool) $listing->is_ad_boosted);
        $this->assertNotNull($listing->ad_boost_expires_at);

        // Boosted listing sorts before the non-boosted ones.
        $first = \App\Models\VendorListing::orderByRaw('is_ad_boosted DESC')->first();
        $this->assertSame($listing->id, $first->id);

        $this->runSchedulerAt($b->booked_until->copy()->addDay()->setTime(12, 0));
        $this->assertSame('completed', $b->fresh()->status->value);
        $listing->refresh();
        $this->assertFalse((bool) $listing->is_ad_boosted);
        $this->assertNull($listing->ad_boost_expires_at);
    }

    public function test_featured_tier_needs_approval_then_boosts_and_serves_popup(): void
    {
        [$slot, $vendor, $listing] = $this->setUpSeeded('listing-boost-featured');
        $svc = app(AdBookingService::class);

        $b = $this->draftWithCreative($slot, $vendor, $listing, 'pending_review');
        $svc->submit($b);
        $this->assertSame('pending_review', $b->fresh()->status->value);
        $this->assertFalse((bool) $listing->fresh()->is_ad_boosted);
        $this->getJson('/api/public/v1/active-popup')->assertJson(['popup' => null]);

        PaidAdCreative::where('paid_ad_booking_id', $b->id)->update(['status' => 'approved', 'approved_at' => now()]);
        $svc->approve($b->fresh(), Admin::first());

        $this->assertSame('scheduled', $b->fresh()->status->value);
        $this->getJson('/api/public/v1/active-popup')->assertJson(['popup' => null]);
        $this->runSchedulerAt(today()->addDay()->setTime(12, 0));

        $this->assertSame('active', $b->fresh()->status->value);
        $this->assertTrue((bool) $listing->fresh()->is_ad_boosted);
        $this->getJson('/api/public/v1/active-popup')->assertOk()
            ->assertJsonPath('popup.id', $b->id)
            ->assertJsonPath('popup.title_en', 'Hello');

        $this->runSchedulerAt($b->booked_until->copy()->addDay()->setTime(12, 0));
        $this->assertFalse((bool) $listing->fresh()->is_ad_boosted);
        $this->getJson('/api/public/v1/active-popup')->assertJson(['popup' => null]);
    }

    /** Move the clock and run the real scheduler job (scheduled -> active / active -> completed). */
    private function runSchedulerAt(\Carbon\Carbon $when): void
    {
        \Carbon\Carbon::setTestNow($when);
        (new \App\Jobs\Ads\PaidAdSchedulerJob)->handle(app(AdBookingService::class));
    }

    protected function tearDown(): void
    {
        \Carbon\Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_popup_is_null_without_popup_bookings(): void
    {
        MarketplaceScenario::make()->build();
        $this->getJson('/api/public/v1/active-popup')->assertOk()->assertJson(['popup' => null]);
    }

    public function test_popup_serves_only_active_booking_on_popup_slot(): void
    {
        [$slot, $vendor, $listing] = $this->setUpSeeded('listing-boost-featured');
        $b = $this->draftWithCreative($slot, $vendor, $listing, 'approved');
        $svc = app(AdBookingService::class);
        $svc->submit($b);
        $svc->approve($b->fresh(), Admin::first());
        $this->runSchedulerAt(today()->addDay()->setTime(12, 0));
        $this->assertSame('active', $b->fresh()->status->value);

        for ($i = 0; $i < 5; $i++) {
            $this->getJson('/api/public/v1/active-popup')->assertOk()
                ->assertJsonPath('popup.id', $b->id)
                ->assertJsonPath('popup.title_en', 'Hello')
                ->assertJsonPath('popup.cta_url', 'https://example.com/p');
        }
    }
}
