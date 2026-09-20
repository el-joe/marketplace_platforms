<?php

namespace Tests\Feature\Ads;

use App\Models\AdCampaign;
use App\Models\AdDailyStat;
use App\Services\Customer\SponsoredProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * CPM billing: cost_per_impression = FLOOR(bid / 1000), charged at
 * impression time. Exercises SponsoredProductService's private
 * cpmCostForImpression()/chargeCpmImpression() helpers directly via
 * reflection, since driving the full inject()/fetchSponsored() query path
 * requires a large amount of unrelated catalog/category scenario setup.
 */
class CpmBillingTest extends TestCase
{
    use RefreshDatabase;

    private function makeCampaign(array $overrides = []): AdCampaign
    {
        $s = MarketplaceScenario::make()->build();

        return AdCampaign::create(array_merge([
            'vendor_id'      => $s->vendor->id,
            'country_id'     => $s->country->id,
            'name'           => 'CPM campaign',
            'type'           => 'cpm',
            'status'         => 'active',
            'budget_total'   => 100_000,
            'budget_daily'   => null,
            'budget_spent_total' => 0,
            'budget_spent_today' => 0,
            'bid'            => 5_000, // cost per impression = floor(5000/1000) = 5
            'targeting_type' => 'auto',
            'quality_score'  => 1,
        ], $overrides));
    }

    private function cost(AdCampaign $campaign): int
    {
        $service = new SponsoredProductService(app(\App\Services\Customer\ListingQueryService::class));
        $method  = new ReflectionMethod($service, 'cpmCostForImpression');
        $method->setAccessible(true);

        return $method->invoke($service, $campaign);
    }

    private function charge(AdCampaign $campaign, string $listingId, string $countryId, int $cost): void
    {
        $service = new SponsoredProductService(app(\App\Services\Customer\ListingQueryService::class));
        $method  = new ReflectionMethod($service, 'chargeCpmImpression');
        $method->setAccessible(true);

        $method->invoke($service, $campaign, $listingId, $countryId, $cost);
    }

    public function test_cpm_cost_is_floor_of_bid_over_1000(): void
    {
        $campaign = $this->makeCampaign(['bid' => 5_500]); // floor(5500/1000) = 5

        $this->assertSame(5, $this->cost($campaign));
    }

    public function test_cpm_impression_decrements_total_and_daily_budget(): void
    {
        $campaign = $this->makeCampaign(['bid' => 5_000, 'budget_daily' => 50]);
        $cost     = $this->cost($campaign);
        $this->assertSame(5, $cost);

        $listingId = $campaign->vendor_id; // any uuid string is fine for this column
        $this->charge($campaign, (string) $listingId, (string) $campaign->country_id, $cost);

        $campaign->refresh();
        $this->assertSame(5, $campaign->budget_spent_total);
        $this->assertSame(5, $campaign->budget_spent_today);

        $stat = AdDailyStat::where('ad_campaign_id', $campaign->id)->first();
        $this->assertNotNull($stat);
        $this->assertSame(1, $stat->impressions);
        $this->assertSame(5, $stat->spend);

        // A second impression accumulates rather than overwrites.
        $this->charge($campaign, (string) $listingId, (string) $campaign->country_id, $cost);
        $campaign->refresh();
        $this->assertSame(10, $campaign->budget_spent_total);
        $this->assertSame(10, $campaign->budget_spent_today);

        $stat->refresh();
        $this->assertSame(2, $stat->impressions);
        $this->assertSame(10, $stat->spend);
    }

    public function test_cpm_billing_stops_once_total_budget_is_exhausted(): void
    {
        $campaign = $this->makeCampaign([
            'bid'                 => 5_000, // cost = 5
            'budget_total'        => 12,
            'budget_spent_total'  => 10,
        ]);

        // 10 + 5 > 12, so charging must be refused (impression can still be
        // shown/recorded elsewhere with cost_charged = 0, but no billing).
        $this->assertSame(0, $this->cost($campaign));
    }

    public function test_cpm_billing_stops_once_daily_budget_is_exhausted(): void
    {
        $campaign = $this->makeCampaign([
            'bid'                 => 5_000, // cost = 5
            'budget_total'        => 100_000,
            'budget_daily'        => 12,
            'budget_spent_today'  => 10,
        ]);

        $this->assertSame(0, $this->cost($campaign));
    }

    public function test_non_cpm_campaign_is_never_charged_by_cpm_logic(): void
    {
        $campaign = $this->makeCampaign(['type' => 'cpc', 'bid' => 5_000]);

        $this->assertSame(0, $this->cost($campaign));
    }

    public function test_zero_bid_never_charges(): void
    {
        $campaign = $this->makeCampaign(['bid' => 999]); // floor(999/1000) = 0

        $this->assertSame(0, $this->cost($campaign));
    }
}
