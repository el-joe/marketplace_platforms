<?php

namespace Tests\Feature\Campaigns;

use App\Models\Admin;
use App\Models\MarketerCampaign;
use App\Models\MarketerCampaignInvitation;
use App\Services\Inventory\InventoryService;
use App\Services\MarketerCampaignService;
use App\Support\Marketer\CampaignOwner;
use App\Support\Marketer\CampaignSource;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\Support\CampaignSampleFixtures as F;
use Tests\Support\MarketerV3Fixtures as V;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * v3 product campaign lifecycle: the influencer's fixed amount is reserved from the Max Commission Budget at creation,
 * paid (net of the commission fee) when the campaign completes and released on reject / cancel; a budget-finished
 * campaign is never resumed by the stock check.
 */
class MarketerCampaignV3LifecycleTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $s;

    private MarketerCampaignService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Notification::fake();
        $this->withoutVite();
        $this->s = MarketplaceScenario::make()->build();
        F::categoryQty($this->s, platform: 1, influencer: 1, affiliate: 0);
        $this->service = app(MarketerCampaignService::class);
    }

    private function pendingCampaign(array $marketers, float $budget): MarketerCampaign
    {
        return $this->service->createCampaign(
            CampaignOwner::vendor($this->s->vendor),
            CampaignSource::vendorListing($this->s->vendorListingFbn->id),
            [
                'country_id' => $this->s->country->id, 'currency' => 'AED', 'max_commission_budget' => $budget,
                'marketer_ids' => array_map(fn ($m) => $m->id, $marketers),
            ]
        );
    }

    public function test_creation_reserves_the_influencer_fixed_amount_from_the_budget(): void
    {
        V::defaults($this->s->country, 'product', influencerFixed: 200);
        $a = F::influencer($this->s);
        $b = F::influencer($this->s);

        $campaign = V::productCampaign($this->s, [$a, $b], 1000);

        $this->assertEquals(400, $campaign->influencer_fixed_reserved);
        $this->assertEquals(600, $campaign->budgetRemaining());
        $invitation = V::invitationOf($campaign, $a);
        $this->assertSame(MarketerCampaignInvitation::FIXED_RESERVED, $invitation->influencer_fixed_status);
        $this->assertEquals(200, $invitation->influencer_fixed_amount);
    }

    public function test_the_amount_is_frozen_at_creation(): void
    {
        $default = V::defaults($this->s->country, 'product', influencerFixed: 200);
        $influencer = F::influencer($this->s);
        $campaign = V::productCampaign($this->s, [$influencer], 1000);

        $default->update(['influencer_fixed_amount' => 999]);

        $this->assertEquals(200, V::invitationOf($campaign, $influencer)->influencer_fixed_amount);
    }

    public function test_influencers_that_do_not_fit_the_budget_reject_the_campaign(): void
    {
        V::defaults($this->s->country, 'product', influencerFixed: 200);
        $a = F::influencer($this->s);
        $b = F::influencer($this->s);

        try {
            $this->pendingCampaign([$a, $b], 300);
            $this->fail('A reservation above the budget must be refused.');
        } catch (\RuntimeException) {
            $this->assertSame(0, MarketerCampaign::where('vendor_listing_id', $this->s->vendorListingFbn->id)->count());
        }
    }

    public function test_completion_pays_the_influencer_net_of_the_commission_fee_once(): void
    {
        V::defaults($this->s->country, 'product', influencerFixed: 200, feePct: 5, feeFixed: 1);
        $influencer = F::influencer($this->s);
        $campaign = V::productCampaign($this->s, [$influencer], 1000);

        $this->service->markCampaignDone($campaign);
        $this->service->markCampaignDone($campaign->fresh());

        $invitation = V::invitationOf($campaign, $influencer);
        $this->assertSame(MarketerCampaignInvitation::FIXED_PAID, $invitation->influencer_fixed_status);
        $this->assertEquals(11, $invitation->influencer_fixed_fee_amount);
        $this->assertEquals(189, $invitation->influencer_fixed_net_amount);
        $this->assertNotNull($invitation->influencer_fixed_wallet_tx_id);
        $this->assertNotNull($campaign->fresh()->influencers_settled_at);
        $wallet = app(WalletService::class)->getOrCreateWallet('marketer', $influencer->id, 'AED');
        $this->assertEquals(189, $wallet->fresh()->balance);
    }

    public function test_a_rejected_invitation_releases_the_reservation(): void
    {
        V::defaults($this->s->country, 'product', influencerFixed: 200);
        $influencer = F::influencer($this->s);
        $campaign = $this->pendingCampaign([$influencer], 1000);
        $this->service->approveCampaign($campaign, Admin::factory()->create());

        $this->assertEquals(200, $campaign->fresh()->influencer_fixed_reserved);
        $this->service->rejectInvitation($campaign->invitations()->sole(), 'busy');

        $this->assertEquals(0, $campaign->fresh()->influencer_fixed_reserved);
        $this->assertSame(MarketerCampaignInvitation::FIXED_RELEASED, $campaign->invitations()->where('marketer_id', $influencer->id)->sole()->influencer_fixed_status);
    }

    public function test_admin_rejection_and_cancellation_release_without_paying(): void
    {
        V::defaults($this->s->country, 'product', influencerFixed: 200);
        $pending = $this->pendingCampaign([F::influencer($this->s)], 1000);
        $this->assertEquals(200, $pending->influencer_fixed_reserved);
        $this->service->rejectCampaign($pending, Admin::factory()->create(), 'no');
        $this->assertEquals(0, $pending->fresh()->influencer_fixed_reserved);

        $influencer = F::influencer($this->s);
        $live = V::productCampaign($this->s, [$influencer], 1000);
        $this->service->cancelCampaign($live);

        $this->assertEquals(0, $live->fresh()->influencer_fixed_reserved);
        $this->assertSame(MarketerCampaignInvitation::FIXED_RELEASED, V::invitationOf($live, $influencer)->influencer_fixed_status);
        $this->assertEquals(0, app(WalletService::class)->getOrCreateWallet('marketer', $influencer->id, 'AED')->fresh()->balance);
    }

    public function test_the_stock_check_never_resumes_a_budget_finished_or_owner_paused_campaign(): void
    {
        $affiliate = F::affiliate($this->s);
        $campaign = V::productCampaign($this->s, [$affiliate], 100);
        $this->assertGreaterThanOrEqual(10, app(InventoryService::class)->availableStock($this->s->vendorListingFbn));

        // Paused (low stock earlier) but the budget has been used up in the meantime.
        $campaign->update(['status' => 'paused', 'commission_budget_spent' => 100]);
        $this->service->checkStockAndUpdateStatus($campaign->fresh());
        $this->assertSame('paused', $campaign->fresh()->status);

        // A campaign the owner paused stays paused as well.
        $campaign->update(['commission_budget_spent' => 0, 'owner_paused_at' => now()]);
        $this->service->checkStockAndUpdateStatus($campaign->fresh());
        $this->assertSame('paused', $campaign->fresh()->status);

        // Control: with budget left and no owner pause, restocked campaigns do resume.
        $campaign->update(['owner_paused_at' => null]);
        $this->service->checkStockAndUpdateStatus($campaign->fresh());
        $this->assertContains($campaign->fresh()->status, ['active', 'auto_approved']);

        // A done campaign stays done.
        $campaign->update(['status' => 'done']);
        $this->service->checkStockAndUpdateStatus($campaign->fresh());
        $this->assertSame('done', $campaign->fresh()->status);
    }
}
