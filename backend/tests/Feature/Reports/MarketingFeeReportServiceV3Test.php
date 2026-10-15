<?php

namespace Tests\Feature\Reports;

use App\Models\MarketerCampaign;
use App\Models\MarketingFeeCountrySetting;
use App\Models\MarketingFeeVerticalSetting;
use App\Models\TravelAgency;
use App\Services\Marketer\CampaignSaleRecorder;
use App\Services\Marketer\ConversionSource;
use App\Services\MarketerCampaignService;
use App\Services\MarketingFeeReportService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\Support\CampaignSampleFixtures as F;
use Tests\Support\MarketerV3Fixtures as V;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * v3 finance report totals from stored snapshots. One trip campaign (budget 1,000):
 *   campaign fee   fixed 10 + 2 % = 30, variable 5 + 1 % = 15 -> 45, collected from the agency wallet;
 *   affiliate      3 bookings of 1,000 at 10 % = 100 gross each (fee 10 % + 2 = 12, net 88): one approved,
 *                  one pending, one reversed;
 *   influencer     fixed 100 paid at completion (fee 12, net 88).
 */
class MarketingFeeReportServiceV3Test extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $s;

    private MarketerCampaign $campaign;

    private TravelAgency $agency;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Notification::fake();
        $this->s = MarketplaceScenario::make()->build();
        $this->agency = V::agency($this->s);
        $package = V::package($this->agency);
        V::defaults($this->s->country, 'travel', influencerFixed: 100, feePct: 10, feeFixed: 2);
        MarketingFeeCountrySetting::create(['country_id' => $this->s->country->id, 'fixed_amount' => 10, 'fixed_percentage' => 2]);
        MarketingFeeVerticalSetting::create(['country_id' => $this->s->country->id, 'vertical' => 'travel', 'fixed_amount' => 5, 'percentage' => 1]);
        $wallets = app(WalletService::class);
        $wallets->credit($wallets->getOrCreateWallet('travel_agency', $this->agency->id, 'AED'), 500, 'test', null, 'topup');

        $affiliate = F::affiliate($this->s);
        V::rule($affiliate, 'travel', rate: 10);
        $influencer = F::influencer($this->s);
        $this->campaign = V::travelCampaign($this->s, $this->agency, $package, [$affiliate, $influencer], 1000);

        $recorder = app(CampaignSaleRecorder::class);
        $invitation = V::invitationOf($this->campaign, $affiliate);
        [$approved, $pending, $reversed] = array_map(
            fn () => $recorder->record($invitation, ConversionSource::travelBooking(V::booking($package)->id), 1000),
            [1, 2, 3]
        );
        $approved->update(['status' => 'approved', 'approved_at' => now(), 'wallet_credited_at' => now()]);
        $recorder->reverse($reversed);

        app(MarketerCampaignService::class)->markCampaignDone($this->campaign->fresh());
    }

    private function rows(array $filters = []): array
    {
        return app(MarketingFeeReportService::class)->platformReport(now()->startOfMonth(), now(), $filters)['rows'];
    }

    private function row(array $rows, string $vertical, string $type): array
    {
        foreach ($rows as $r) {
            if ($r['vertical'] === $vertical && $r['marketer_type'] === $type && $r['currency'] === 'AED') {
                return $r;
            }
        }
        $this->fail("No report row for {$vertical}/{$type}");
    }

    public function test_the_campaign_fee_is_reported_from_the_creation_snapshot(): void
    {
        $this->assertSame('paid', $this->campaign->fresh()->campaign_fee_status);
        $this->assertEquals(45, $this->campaign->fresh()->marketing_fee_total);

        $row = $this->row($this->rows(), 'travel', '');

        $this->assertEquals(1, $row['campaigns']);
        $this->assertEquals(30, $row['campaign_fee_fixed']);
        $this->assertEquals(15, $row['campaign_fee_variable']);
        $this->assertEquals(45, $row['campaign_fee_earned']);
        $this->assertEquals(0, $row['campaign_fee_pending']);
    }

    public function test_commissions_are_split_by_marketer_type_and_state(): void
    {
        $rows = $this->rows();

        $affiliate = $this->row($rows, 'travel', 'affiliate');
        $this->assertEquals(100, $affiliate['commission_pending']);
        $this->assertEquals(100, $affiliate['commission_approved']);
        $this->assertEquals(100, $affiliate['commission_reversed']);
        $this->assertEquals(12, $affiliate['commission_fee_earned']);
        $this->assertEquals(88, $affiliate['commission_paid_net']);

        $influencer = $this->row($rows, 'travel', 'influencer');
        $this->assertEquals(100, $influencer['commission_approved']);
        $this->assertEquals(12, $influencer['commission_fee_earned']);
        $this->assertEquals(88, $influencer['commission_paid_net']);
    }

    public function test_the_total_row_is_the_sum_of_the_lines_and_filters_narrow_them(): void
    {
        $total = $this->row($this->rows(), 'total', '');

        $this->assertEquals(45, $total['campaign_fee_earned']);
        $this->assertEquals(200, $total['commission_approved']);
        $this->assertEquals(24, $total['commission_fee_earned']);
        $this->assertEquals(176, $total['commission_paid_net']);
        $this->assertEquals(100, $total['commission_pending']);
        $this->assertEquals(100, $total['commission_reversed']);

        $this->assertSame([], array_filter($this->rows(['vertical' => 'product']), fn ($r) => $r['vertical'] === 'travel'));
        $onlyInfluencers = $this->rows(['marketer_type' => 'influencer']);
        $this->assertEmpty(array_filter($onlyInfluencers, fn ($r) => $r['marketer_type'] === 'affiliate'));
    }

    public function test_the_range_only_counts_events_inside_it(): void
    {
        $rows = app(MarketingFeeReportService::class)->platformReport(now()->subYear()->startOfMonth(), now()->subYear()->endOfMonth())['rows'];

        $this->assertSame([], $rows);
    }

    public function test_the_campaign_fee_report_shows_subtotal_fee_parts_and_collection_state(): void
    {
        $rows = app(MarketingFeeReportService::class)->campaignFeeReport(now()->startOfMonth(), now(), ['vertical' => 'travel']);
        $row = collect($rows)->first(fn ($r) => $r['vertical'] === 'travel' && $r['currency'] === 'AED');

        $this->assertEquals(1000, $row['subtotal']);
        $this->assertEquals([30, 15, 45], [$row['fixed_fee'], $row['variable_fee'], $row['campaign_fee']]);
        $this->assertEquals(1045, $row['grand_total']);
        $this->assertEquals([45, 0], [$row['collected'], $row['pending']]);
    }
}
