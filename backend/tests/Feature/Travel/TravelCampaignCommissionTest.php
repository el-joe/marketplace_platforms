<?php

namespace Tests\Feature\Travel;

use App\Enums\TravelBookingStatus;
use App\Jobs\ApproveMarketerConversionsJob;
use App\Jobs\CompleteTravelBookingsJob;
use App\Models\MarketerCampaign;
use App\Models\MarketerCampaignConversion;
use App\Models\TravelAgency;
use App\Models\TravelPackage;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\Support\CampaignSampleFixtures as F;
use Tests\Support\MarketerV3Fixtures as V;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * Trip bookings attributed to a marketer campaign: commission earned when the booking is confirmed, approved only after
 * the trip is completed (CompleteTravelBookingsJob), reversed on cancellation.
 */
class TravelCampaignCommissionTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $s;

    private TravelAgency $agency;

    private TravelPackage $package;

    private MarketerCampaign $campaign;

    private $affiliate;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Notification::fake();
        $this->withoutVite();
        $this->s = MarketplaceScenario::make()->build();
        $this->agency = V::agency($this->s);
        $this->package = V::package($this->agency);
        V::defaults($this->s->country, 'travel', feePct: 10, feeFixed: 2);
        $this->affiliate = F::affiliate($this->s);
        V::rule($this->affiliate, 'travel', rate: 10);
        $this->campaign = V::travelCampaign($this->s, $this->agency, $this->package, [$this->affiliate], 1000);
    }

    private function conversionOf($booking): ?MarketerCampaignConversion
    {
        return MarketerCampaignConversion::where('travel_booking_id', $booking->id)->first();
    }

    private function approveJob(): void
    {
        app()->call([new ApproveMarketerConversionsJob, 'handle']);
    }

    private function completeJob(): void
    {
        app()->call([new CompleteTravelBookingsJob, 'handle']);
    }

    public function test_a_pending_booking_earns_nothing_and_confirming_it_creates_the_conversion(): void
    {
        $booking = V::booking($this->package, $this->campaign, $this->affiliate);
        $this->assertNull($this->conversionOf($booking));

        $booking->update(['status' => TravelBookingStatus::Confirmed]);

        $conversion = $this->conversionOf($booking);
        $this->assertSame(MarketerCampaignConversion::TYPE_TRAVEL_BOOKING, $conversion->conversion_type);
        $this->assertSame('pending', $conversion->status);
        $this->assertEquals(1000, $conversion->sale_amount);
        $this->assertEquals([100, 12, 88], [$conversion->gross_commission_amount, $conversion->commission_fee_amount, $conversion->net_commission_amount]);
        $this->assertEquals(100, $this->campaign->fresh()->commission_budget_spent);
    }

    public function test_a_booking_without_attribution_or_with_a_foreign_marketer_earns_nothing(): void
    {
        $plain = V::booking($this->package, null, null, ['status' => TravelBookingStatus::Confirmed]);
        $stranger = F::affiliate($this->s);
        $foreign = V::booking($this->package, $this->campaign, $stranger, ['status' => TravelBookingStatus::Confirmed, 'marketer_id' => $stranger->id]);

        $this->assertNull($this->conversionOf($plain));
        $this->assertNull($this->conversionOf($foreign));
    }

    public function test_approval_waits_for_the_trip_to_be_completed(): void
    {
        $booking = V::booking($this->package, $this->campaign, $this->affiliate, ['status' => TravelBookingStatus::Confirmed]);

        $this->approveJob();
        $this->assertSame('pending', $this->conversionOf($booking)->status, 'confirmed but the trip has not happened yet');

        // The trip is over: the nightly job completes the booking, then the approval job approves the commission.
        $this->package->forceFill(['departure_date' => now()->subDays(10), 'return_date' => now()->subDays(3)])->save();
        $this->completeJob();

        $booking->refresh();
        $this->assertSame(TravelBookingStatus::Completed, $booking->status);
        $this->assertNotNull($booking->completed_at);
        $this->assertSame('pending', $this->conversionOf($booking)->status);

        $this->approveJob();

        $conversion = $this->conversionOf($booking);
        $this->assertSame('approved', $conversion->status);
        $this->assertNotNull($conversion->wallet_credited_at);
        $wallet = app(WalletService::class)->getOrCreateWallet('marketer', $this->affiliate->id, $conversion->currency);
        $this->assertEquals(88, $wallet->fresh()->pending_balance, 'the NET commission is parked, the platform fee is already taken');
        $this->assertSame(1, MarketerCampaignConversion::where('campaign_id', $this->campaign->id)->count());
    }

    public function test_the_nightly_job_only_completes_confirmed_bookings_of_finished_trips(): void
    {
        $future = V::booking($this->package, null, null, ['status' => TravelBookingStatus::Confirmed]);
        $pastPackage = V::package($this->agency, ['departure_date' => now()->subDays(10), 'return_date' => now()->subDays(2)]);
        $past = V::booking($pastPackage, null, null, ['status' => TravelBookingStatus::Confirmed]);
        $unpaid = V::booking($pastPackage);
        $cancelled = V::booking($pastPackage, null, null, ['status' => TravelBookingStatus::Cancelled]);

        $this->completeJob();
        $this->completeJob();

        $this->assertSame(TravelBookingStatus::Confirmed, $future->fresh()->status);
        $this->assertSame(TravelBookingStatus::Completed, $past->fresh()->status);
        $this->assertSame(TravelBookingStatus::PendingDocuments, $unpaid->fresh()->status);
        $this->assertSame(TravelBookingStatus::Cancelled, $cancelled->fresh()->status);
    }

    public function test_cancelling_the_booking_reverses_the_commission_and_frees_the_budget(): void
    {
        $booking = V::booking($this->package, $this->campaign, $this->affiliate, ['status' => TravelBookingStatus::Confirmed]);
        $this->assertEquals(100, $this->campaign->fresh()->commission_budget_spent);

        $booking->update(['status' => TravelBookingStatus::Cancelled]);

        $this->assertSame('reversed', $this->conversionOf($booking)->status);
        $this->assertEquals(0, $this->campaign->fresh()->commission_budget_spent);
    }

    public function test_cancelling_after_the_commission_was_approved_takes_it_out_of_the_pending_balance(): void
    {
        $this->package->forceFill(['departure_date' => now()->subDays(10), 'return_date' => now()->subDays(3)])->save();
        $booking = V::booking($this->package, $this->campaign, $this->affiliate, ['status' => TravelBookingStatus::Confirmed]);
        $this->completeJob();
        $this->approveJob();
        $wallet = app(WalletService::class)->getOrCreateWallet('marketer', $this->affiliate->id, 'AED');
        $this->assertEquals(88, $wallet->fresh()->pending_balance);

        $booking->fresh()->update(['status' => TravelBookingStatus::Cancelled]);

        $this->assertSame('reversed', $this->conversionOf($booking)->status);
        $this->assertEquals(0, $wallet->fresh()->pending_balance);
    }
}
