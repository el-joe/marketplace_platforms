<?php

namespace Tests\Feature\Ads;

use App\Models\AdCampaign;
use App\Models\AdClick;
use App\Models\AdImpression;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\CallQueuedClosure;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * CPC billing: clicking on a CPC campaign's ad must charge campaign->bid
 * (AdCampaign::budget_spent_total / budget_spent_today incremented, and
 * AdClick.cost set to the bid). CPM campaigns bill on impression, not
 * click, so a click on a CPM campaign's ad must never be charged.
 *
 * Regression test for a bug where the controller compared the AdCampaign
 * `type`/`status` model attributes (cast to backed enums) against plain
 * strings (e.g. `$campaign->type === 'cpc'`), which is always false for an
 * enum instance vs. a string — so CPC clicks were never charged, and (via
 * the status check) clicks were never even recorded at all.
 */
class CpcBillingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * MarketplaceScenario doesn't set Country::site_code (it's used by other
     * portal routes, nullable, not needed for its own scenarios), but the
     * {country} route segment for this endpoint resolves via site_code
     * (App\Http\Middleware\DetectCountry), so tests hitting the HTTP route
     * must assign one themselves.
     */
    private function assignSiteCode(MarketplaceScenario $s): string
    {
        $siteCode = 'ae' . Str::random(6);
        $s->country->update(['site_code' => $siteCode]);

        return $siteCode;
    }

    /**
     * The click() controller action does its billing work inside a closure
     * dispatched with ->afterResponse(). That's normally fine in production
     * (the closure is serialized onto the "sync"/"database" queue and run by
     * the terminating callback), but Illuminate\Http\Request is not safely
     * serializable end-to-end in the test process here, so we fake the bus,
     * capture the pushed CallQueuedClosure job, and run it directly — this
     * still exercises the real controller code, just without going through
     * closure (de)serialization.
     */
    private function hitClickEndpoint(string $siteCode, AdImpression $impression): void
    {
        Bus::fake([CallQueuedClosure::class]);

        $this->postJson("/api/customer/v1/{$siteCode}/ads/sponsored/click", [
            'impression_id'     => $impression->id,
            'vendor_listing_id' => $impression->vendor_listing_id,
        ])->assertOk();

        $jobs = Bus::dispatchedAfterResponse(CallQueuedClosure::class);
        $this->assertCount(1, $jobs, 'Expected exactly one after-response job to be dispatched by click().');

        $jobs->first()->handle(app());
    }

    private function makeCampaign(MarketplaceScenario $s, array $overrides = []): AdCampaign
    {
        return AdCampaign::create(array_merge([
            'vendor_id'      => $s->vendor->id,
            'country_id'     => $s->country->id,
            'name'           => 'CPC campaign',
            'type'           => 'cpc',
            'status'         => 'active',
            'budget_total'   => 100_000,
            'budget_daily'   => null,
            'budget_spent_total' => 0,
            'budget_spent_today' => 0,
            'bid'            => 250,
            'targeting_type' => 'auto',
            'quality_score'  => 1,
        ], $overrides));
    }

    private function makeImpression(AdCampaign $campaign, MarketplaceScenario $s): AdImpression
    {
        return AdImpression::create([
            'ad_campaign_id'     => $campaign->id,
            'vendor_listing_id'  => $s->vendorListingFbp->id,
            'session_id'         => (string) Str::random(26),
            'placement_code'     => 'product_page_cross_sell',
            'position_shown'     => 1,
            'bid_at_impression'  => $campaign->bid,
            'quality_score_at_impression' => 1,
            'device_type'        => 'mobile',
            'was_clicked'        => false,
            'was_converted'      => false,
            'cost_charged'       => 0,
            'country_id'         => $s->country->id,
            'shown_at'           => now(),
        ]);
    }

    public function test_clicking_a_cpc_campaign_charges_the_bid(): void
    {
        $s = MarketplaceScenario::make()->build();
        $siteCode = $this->assignSiteCode($s);
        $campaign = $this->makeCampaign($s, ['bid' => 250]);
        $impression = $this->makeImpression($campaign, $s);

        $this->hitClickEndpoint($siteCode, $impression);

        $campaign->refresh();
        $this->assertSame(250, $campaign->budget_spent_total);
        $this->assertSame(250, $campaign->budget_spent_today);

        $click = AdClick::where('ad_impression_id', $impression->id)->first();
        $this->assertNotNull($click, 'A CPC click must be recorded.');
        $this->assertSame(250, $click->cost);

        $impression->refresh();
        $this->assertTrue((bool) $impression->was_clicked);
        $this->assertSame(250, $impression->cost_charged);
    }

    public function test_clicking_a_cpc_campaign_twice_accumulates_spend(): void
    {
        $s = MarketplaceScenario::make()->build();
        $siteCode = $this->assignSiteCode($s);
        $campaign = $this->makeCampaign($s, ['bid' => 250]);

        $firstImpression = $this->makeImpression($campaign, $s);
        $this->hitClickEndpoint($siteCode, $firstImpression);

        $secondImpression = $this->makeImpression($campaign, $s);
        $this->hitClickEndpoint($siteCode, $secondImpression);

        $campaign->refresh();
        $this->assertSame(500, $campaign->budget_spent_total);
        $this->assertSame(500, $campaign->budget_spent_today);
    }

    public function test_clicking_a_cpm_campaign_is_not_charged_on_click(): void
    {
        $s = MarketplaceScenario::make()->build();
        $siteCode = $this->assignSiteCode($s);
        $campaign = $this->makeCampaign($s, [
            'type'  => 'cpm',
            'bid'   => 5_000,
        ]);
        $impression = $this->makeImpression($campaign, $s);

        $this->hitClickEndpoint($siteCode, $impression);

        $campaign->refresh();
        $this->assertSame(0, $campaign->budget_spent_total);
        $this->assertSame(0, $campaign->budget_spent_today);

        $click = AdClick::where('ad_impression_id', $impression->id)->first();
        $this->assertNotNull($click);
        $this->assertSame(0, $click->cost);

        $impression->refresh();
        $this->assertTrue((bool) $impression->was_clicked);
        $this->assertSame(0, $impression->cost_charged);
    }

    public function test_a_paused_campaign_is_not_charged_or_recorded_on_click(): void
    {
        $s = MarketplaceScenario::make()->build();
        $siteCode = $this->assignSiteCode($s);
        $campaign = $this->makeCampaign($s, [
            'status' => 'paused',
        ]);
        $impression = $this->makeImpression($campaign, $s);

        $this->hitClickEndpoint($siteCode, $impression);

        $campaign->refresh();
        $this->assertSame(0, $campaign->budget_spent_total);

        $this->assertNull(AdClick::where('ad_impression_id', $impression->id)->first());

        $impression->refresh();
        $this->assertFalse((bool) $impression->was_clicked);
    }
}
