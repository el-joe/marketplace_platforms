<?php

namespace Tests\Feature\Campaigns;

use App\Models\MarketerCampaign;
use App\Models\MarketerCampaignConversion;
use App\Models\TravelAgency;
use App\Models\TravelPackage;
use App\Services\Marketer\CampaignSaleRecorder;
use App\Services\Marketer\ConversionSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\Support\CampaignSampleFixtures as F;
use Tests\Support\MarketerV3Fixtures as V;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * CampaignSaleRecorder, exercised on a trip campaign (many conversions per campaign, cheap sources):
 * snapshot of gross / fee / net, idempotency, budget exhaustion -> done, influencers earn no per-sale conversion,
 * reversal frees the budget and never reopens a finished campaign.
 */
class CampaignSaleRecorderTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $s;

    private TravelAgency $agency;

    private TravelPackage $package;

    private CampaignSaleRecorder $recorder;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Notification::fake();
        $this->withoutVite();
        $this->s = MarketplaceScenario::make()->build();
        $this->agency = V::agency($this->s);
        $this->package = V::package($this->agency);
        $this->recorder = app(CampaignSaleRecorder::class);
    }

    /** An affiliate on a 10 % rule, accepted on a trip campaign of the given budget. */
    private function affiliateCampaign(float $budget): array
    {
        $affiliate = F::affiliate($this->s);
        V::rule($affiliate, 'travel', rate: 10);
        $campaign = V::travelCampaign($this->s, $this->agency, $this->package, [$affiliate], $budget);

        return [$campaign, V::invitationOf($campaign, $affiliate), $affiliate];
    }

    private function record($invitation, float $basis = 1000): ?MarketerCampaignConversion
    {
        $booking = V::booking($this->package);

        return $this->recorder->record($invitation, ConversionSource::travelBooking($booking->id), $basis);
    }

    public function test_a_conversion_snapshots_basis_gross_fee_and_net(): void
    {
        V::defaults($this->s->country, 'travel', feePct: 10, feeFixed: 2);
        [$campaign, $invitation] = $this->affiliateCampaign(1000);

        $conversion = $this->record($invitation);

        $this->assertSame('pending', $conversion->status);
        $this->assertSame(MarketerCampaignConversion::TYPE_TRAVEL_BOOKING, $conversion->conversion_type);
        $this->assertEquals(1000, $conversion->sale_amount);
        $this->assertEquals(100, $conversion->gross_commission_amount);
        $this->assertEquals(12, $conversion->commission_fee_amount);
        $this->assertEquals(88, $conversion->net_commission_amount);
        $this->assertSame('affiliate', $conversion->marketer_type);
        $this->assertEquals(100, $campaign->fresh()->commission_budget_spent);
        $this->assertSame(1, $invitation->fresh()->total_conversions);
    }

    public function test_one_source_produces_one_conversion_ever(): void
    {
        [$campaign, $invitation] = $this->affiliateCampaign(1000);
        $booking = V::booking($this->package);
        $source = ConversionSource::travelBooking($booking->id);

        $first = $this->recorder->record($invitation, $source, 1000);
        $second = $this->recorder->record($invitation, $source, 1000);

        $this->assertNotNull($first);
        $this->assertNull($second);
        $this->assertSame(1, MarketerCampaignConversion::where('campaign_id', $campaign->id)->count());
        $this->assertEquals(100, $campaign->fresh()->commission_budget_spent);
    }

    public function test_reaching_the_budget_marks_the_campaign_done_and_nothing_more_is_recorded(): void
    {
        [$campaign, $invitation] = $this->affiliateCampaign(200);

        $this->assertNotNull($this->record($invitation));
        $this->assertSame('active', $campaign->fresh()->status);

        $this->assertNotNull($this->record($invitation));
        $this->assertSame('done', $campaign->fresh()->status);
        $this->assertEquals(200, $campaign->fresh()->commission_budget_spent);

        $this->assertNull($this->record($invitation));
        $this->assertSame(2, MarketerCampaignConversion::where('campaign_id', $campaign->id)->count());
    }

    public function test_the_last_commission_is_shortened_to_what_is_left_of_the_budget(): void
    {
        [$campaign, $invitation] = $this->affiliateCampaign(150);

        $this->record($invitation);
        $last = $this->record($invitation);

        $this->assertEquals(50, $last->gross_commission_amount);
        $this->assertEquals(150, $campaign->fresh()->commission_budget_spent);
        $this->assertSame('done', $campaign->fresh()->status);
    }

    public function test_influencers_never_get_per_sale_conversions(): void
    {
        V::defaults($this->s->country, 'travel', influencerFixed: 100);
        $influencer = F::influencer($this->s);
        $campaign = V::travelCampaign($this->s, $this->agency, $this->package, [$influencer], 1000);

        $this->assertNull($this->record(V::invitationOf($campaign, $influencer)));
        $this->assertSame(0, MarketerCampaignConversion::where('campaign_id', $campaign->id)->count());
        $this->assertEquals(0, $campaign->fresh()->commission_budget_spent);
    }

    public function test_only_live_campaigns_record_conversions(): void
    {
        [$campaign, $invitation] = $this->affiliateCampaign(1000);
        $campaign->update(['status' => 'paused']);

        $this->assertNull($this->record($invitation));
    }

    public function test_reversal_frees_the_budget_and_is_idempotent(): void
    {
        [$campaign, $invitation] = $this->affiliateCampaign(1000);
        $conversion = $this->record($invitation);

        $this->assertTrue($this->recorder->reverse($conversion));
        $this->assertFalse($this->recorder->reverse($conversion->fresh()));

        $conversion->refresh();
        $this->assertSame('reversed', $conversion->status);
        $this->assertNotNull($conversion->reversed_at);
        $this->assertEquals(0, $campaign->fresh()->commission_budget_spent);
        $this->assertSame(0, $invitation->fresh()->total_conversions);
    }

    public function test_reversal_does_not_reopen_a_finished_campaign_and_a_paid_conversion_is_left_alone(): void
    {
        [$campaign, $invitation] = $this->affiliateCampaign(100);
        $conversion = $this->record($invitation);
        $this->assertSame('done', $campaign->fresh()->status);

        $conversion->update(['status' => 'paid']);
        $this->assertFalse($this->recorder->reverse($conversion->fresh()));

        $conversion->update(['status' => 'pending']);
        $this->assertTrue($this->recorder->reverse($conversion->fresh()));
        $this->assertSame('done', $campaign->fresh()->status);
        $this->assertInstanceOf(MarketerCampaign::class, $campaign->fresh());
    }
}
