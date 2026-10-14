<?php

namespace Tests\Feature\Campaigns;

use App\Models\Admin;
use App\Models\MarketerCommissionCountrySetting;
use App\Services\MarketerCampaignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\Support\CampaignSampleFixtures as F;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/** The campaign-level per-unit commission is the category-tab AFFILIATE default (any mode), never an influencer value. */
class MarketerCampaignDefaultCommissionTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $s;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Notification::fake();
        $this->s = MarketplaceScenario::make()->build();
        Admin::factory()->create();
        F::categoryQty($this->s, platform: 1, influencer: 1, affiliate: 0);
    }

    private function default(string $mode, float $rate, float $flat, float $sharePct = 0): void
    {
        MarketerCommissionCountrySetting::create([
            'category_id' => $this->s->category->id, 'country_id' => $this->s->country->id, 'currency' => 'AED',
            'influencer_fee_share_pct' => $sharePct,
            'affiliate_commission_mode' => $mode, 'affiliate_commission_rate' => $rate, 'affiliate_commission_flat' => $flat,
        ]);
    }

    public function test_a_fixed_default_is_stored_per_unit_at_creation(): void
    {
        $this->default('fixed', 0, 7);

        $campaign = F::campaign($this->s, [F::affiliate($this->s)], 0);

        $this->assertEquals(7, $campaign->marketer_commission_amount);
    }

    public function test_a_percentage_default_is_estimated_on_the_listing_price(): void
    {
        $this->default('percentage', 10, 0);

        $campaign = F::campaign($this->s, [F::affiliate($this->s)], 0);

        $this->assertEquals(round((float) $this->s->vendorListingFbn->price * 0.10, 2), (float) $campaign->marketer_commission_amount);
    }

    public function test_a_both_default_adds_percentage_and_flat(): void
    {
        $this->default('both', 10, 3);

        $campaign = F::campaign($this->s, [F::affiliate($this->s)], 0);

        $this->assertEquals(round((float) $this->s->vendorListingFbn->price * 0.10 + 3, 2), (float) $campaign->marketer_commission_amount);
    }

    public function test_only_a_fee_share_configured_leaves_the_campaign_amount_at_zero(): void
    {
        $this->default('percentage', 0, 0, sharePct: 20);

        $campaign = F::campaign($this->s, [F::influencer($this->s)], 0);

        $this->assertEquals(0, $campaign->marketer_commission_amount);
        $this->expectException(\RuntimeException::class);
        app(MarketerCampaignService::class)->approveCampaign($campaign, Admin::first());
    }

    public function test_auto_approval_uses_the_percentage_default(): void
    {
        $this->default('percentage', 10, 0);
        $campaign = F::campaign($this->s, [F::affiliate($this->s)], 0);
        $campaign->update(['marketer_commission_amount' => 0]);

        app(MarketerCampaignService::class)->autoApproveCampaign($campaign->id);

        $campaign->refresh();
        $this->assertSame('auto_approved', $campaign->status);
        $this->assertGreaterThan(0, (float) $campaign->marketer_commission_amount);
    }
}
