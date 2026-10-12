<?php

namespace Tests\Feature\Campaigns;

use App\Models\Admin;
use App\Models\MarketerCampaignInvitation;
use App\Models\MarketerCampaignSample;
use App\Services\Campaign\CampaignSampleService;
use App\Services\Inventory\InventoryService;
use App\Services\MarketerCampaignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\Support\CampaignSampleFixtures as F;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * P8 (R5): ONE fixed admin sample + one sample per influencer created with the
 * invitation, affiliate row, replacement / cancel handling, stock gate, and
 * the "stock out once, commission once" dispatch.
 */
class CampaignSamplesServiceTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $s;

    private MarketerCampaignService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Notification::fake();
        $this->s = MarketplaceScenario::make()->build();
        $this->svc = app(MarketerCampaignService::class);
        Admin::factory()->create();
    }

    private function approve($campaign): void
    {
        $this->svc->approveCampaign($campaign, Admin::factory()->create());
    }

    private function live($campaign)
    {
        return $campaign->samples()->live()->get();
    }

    public function test_two_influencers_give_three_samples_and_influencer_samples_follow_the_invitation(): void
    {
        F::categoryQty($this->s, platform: 0, influencer: 2);
        $a = F::influencer($this->s);
        $b = F::influencer($this->s);

        $campaign = F::campaign($this->s, [$a, $b]);

        // Before approval: only the single fixed admin sample (category qty 0 -> minimum 1).
        $this->assertSame(0, $campaign->invitations()->count());
        $this->assertCount(1, $this->live($campaign));
        $admin = $campaign->samples()->where('sample_type', 'admin')->sole();
        $this->assertSame('platform', $admin->sample_owner);
        $this->assertSame(1, $admin->quantity);
        $this->assertNull($admin->invitation_id);
        $this->assertSame(1, $campaign->fresh()->platform_sample_qty_snapshot);

        // Invitations dispatched -> one pending sample per influencer, qty from the category.
        $this->approve($campaign);
        $samples = $this->live($campaign->fresh());
        $this->assertCount(3, $samples, '2 influencers => 3 samples');

        foreach ([$a, $b] as $m) {
            $inv = $campaign->invitations()->where('marketer_id', $m->id)->sole();
            $this->assertSame('pending', $inv->status, 'sample exists although the invitation is not accepted yet');
            $sample = MarketerCampaignSample::where('invitation_id', $inv->id)->sole();
            $this->assertSame('influencer', $sample->sample_type);
            $this->assertSame('marketer', $sample->sample_owner);
            $this->assertSame(2, $sample->quantity);
            $this->assertSame('pending', $sample->status);
        }

        // Accepting must not create a second one.
        $inv = $campaign->invitations()->where('marketer_id', $a->id)->sole();
        $this->svc->acceptInvitation($inv);
        $this->assertCount(3, $this->live($campaign->fresh()));
    }

    public function test_minimum_quantities_come_from_the_admin_settings(): void
    {
        F::categoryQty($this->s, platform: 0, influencer: 0);
        F::setting('marketer_campaign_min_platform_sample_qty', 3);
        F::setting('marketer_campaign_min_influencer_sample_qty', 2);
        $inf = F::influencer($this->s);

        $campaign = F::campaign($this->s, [$inf]);
        $this->approve($campaign);

        $this->assertSame(3, $campaign->samples()->where('sample_type', 'admin')->sole()->quantity);
        $this->assertSame(2, $campaign->samples()->where('sample_type', 'influencer')->sole()->quantity);

        // A category quantity above the minimum wins.
        $this->s->category->update(['platform_sample_qty' => 5]);
        $second = F::campaign($this->s, [F::influencer($this->s)]);
        $this->assertSame(5, $second->samples()->where('sample_type', 'admin')->sole()->quantity);
    }

    public function test_affiliates_get_one_platform_row_not_one_per_affiliate(): void
    {
        F::categoryQty($this->s, platform: 1, influencer: 1, affiliate: 3);
        $inf = F::influencer($this->s);
        $aff1 = F::affiliate($this->s);
        $aff2 = F::affiliate($this->s);

        $campaign = F::campaign($this->s, [$inf, $aff1, $aff2]);
        $this->approve($campaign);

        $affiliateRows = $campaign->samples()->where('sample_type', 'affiliate')->get();
        $this->assertCount(1, $affiliateRows);
        $this->assertSame('platform', $affiliateRows->first()->sample_owner);
        $this->assertSame(3, $affiliateRows->first()->quantity);
        $this->assertNull($affiliateRows->first()->invitation_id);
        // admin + influencer + one affiliate row; no per-affiliate sample.
        $this->assertCount(3, $this->live($campaign->fresh()));

        // Inviting another affiliate later does not add another row.
        $aff3 = F::affiliate($this->s);
        $this->svc->inviteMarketers($campaign->fresh(), [$aff3->id]);
        $this->assertSame(1, $campaign->samples()->where('sample_type', 'affiliate')->count());
    }

    public function test_no_affiliate_row_when_the_admin_quantity_is_zero_or_no_affiliate_targeted(): void
    {
        F::categoryQty($this->s, platform: 1, influencer: 1, affiliate: 0);
        $campaign = F::campaign($this->s, [F::influencer($this->s), F::affiliate($this->s)]);
        $this->assertSame(0, $campaign->samples()->where('sample_type', 'affiliate')->count());

        F::categoryQty($this->s, platform: 1, influencer: 1, affiliate: 4);
        $influencerOnly = F::campaign($this->s, [F::influencer($this->s)]);
        $this->assertSame(0, $influencerOnly->samples()->where('sample_type', 'affiliate')->count());
    }

    public function test_replacing_an_influencer_repoints_the_undispatched_sample_without_duplicating(): void
    {
        F::categoryQty($this->s, platform: 1, influencer: 2);
        $first = F::influencer($this->s);
        $spare = F::influencer($this->s);
        $campaign = F::campaign($this->s, [$first]);
        $this->approve($campaign);

        $oldInv = $campaign->invitations()->where('marketer_id', $first->id)->sole();
        $sample = MarketerCampaignSample::where('invitation_id', $oldInv->id)->sole();

        $this->svc->rejectInvitation($oldInv, 'busy');

        $newInv = MarketerCampaignInvitation::where('replaced_invitation_id', $oldInv->id)->sole();
        $this->assertSame($spare->id, $newInv->marketer_id);

        $this->assertSame($newInv->id, $sample->fresh()->invitation_id, 'same row moved to the replacement');
        $this->assertSame('pending', $sample->fresh()->status);
        $this->assertCount(2, $this->live($campaign->fresh()), 'admin + the moved influencer sample, no duplicate');
        $this->assertSame(0, $campaign->samples()->where('status', 'cancelled')->count());

        // Timeout of the replacement behaves the same way (no spare left -> cancelled).
        $this->svc->handleInvitationTimeout($newInv->id);
        $this->assertSame('cancelled', $sample->fresh()->status);
        $this->assertNotNull($sample->fresh()->cancelled_at);
        $this->assertCount(1, $this->live($campaign->fresh()));
    }

    public function test_rejection_without_a_replacement_cancels_the_sample(): void
    {
        F::categoryQty($this->s, platform: 1, influencer: 1);
        $only = F::influencer($this->s);
        $campaign = F::campaign($this->s, [$only]);
        $this->approve($campaign);
        $inv = $campaign->invitations()->sole();
        $sample = MarketerCampaignSample::where('invitation_id', $inv->id)->sole();

        $this->svc->rejectInvitation($inv);

        $this->assertSame('cancelled', $sample->fresh()->status);
    }

    public function test_cancelling_the_campaign_cancels_undispatched_samples_but_keeps_dispatched_ones(): void
    {
        F::categoryQty($this->s, platform: 1, influencer: 1);
        $campaign = F::campaign($this->s, [F::influencer($this->s), F::influencer($this->s)]);
        $this->approve($campaign);

        $admin = $campaign->samples()->where('sample_type', 'admin')->sole();
        app(CampaignSampleService::class)->dispatch($admin);

        $this->svc->cancelCampaign($campaign->fresh());

        $this->assertSame('dispatched', $admin->fresh()->status);
        $this->assertNotNull($admin->fresh()->stock_deducted_at);
        $this->assertSame(2, $campaign->samples()->where('status', 'cancelled')->count());
    }

    public function test_stock_gate_subtracts_every_planned_sample(): void
    {
        // 40 units on hand, minimum 10 must stay sellable: samples may use at most 30.
        F::categoryQty($this->s, platform: 1, influencer: 15, minStock: 10);
        $two = [F::influencer($this->s), F::influencer($this->s)];

        // 1 + 2*15 = 31 > 30 -> refused with the numbers in the message.
        try {
            F::campaign($this->s, $two);
            $this->fail('31 sample units must not fit next to a minimum of 10');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Insufficient stock', $e->getMessage());
            $this->assertStringContainsString('31', $e->getMessage());
        }
        $this->assertSame(0, $this->s->vendorListingFbn->fresh()->campaign_enabled ? 1 : 0);

        // 1 + 2*14 = 29 fits.
        F::categoryQty($this->s, platform: 1, influencer: 14, minStock: 10);
        $campaign = F::campaign($this->s, $two);
        $this->assertSame(29, app(CampaignSampleService::class)->plan($this->s->category->fresh(), 2, false)['total']);
        $this->approve($campaign);
        $this->assertSame(29, (int) $campaign->samples()->live()->sum('quantity'));

        // A second campaign on the same listing sees the first one's undispatched samples.
        F::categoryQty($this->s, platform: 1, influencer: 14, minStock: 10);
        $this->expectException(\RuntimeException::class);
        F::campaign($this->s, [F::influencer($this->s)]);
    }

    public function test_dispatch_takes_stock_and_snapshots_the_commission_exactly_once(): void
    {
        F::categoryQty($this->s, platform: 1, influencer: 2);
        $campaign = F::campaign($this->s, [F::influencer($this->s)]);
        $this->approve($campaign);
        $inv = $campaign->invitations()->sole();
        $this->svc->acceptInvitation($inv);
        $sample = MarketerCampaignSample::where('invitation_id', $inv->id)->sole();

        $listing = $this->s->vendorListingFbn;
        $inventory = app(InventoryService::class);
        $before = $inventory->availableStock($listing);

        $svc = app(CampaignSampleService::class);
        $this->assertTrue($svc->dispatch($sample));
        $this->assertFalse($svc->dispatch($sample->fresh()), 'second dispatch is a no-op');

        $this->assertSame($before - 2, $inventory->availableStock($listing));
        $sample->refresh();
        $this->assertSame('dispatched', $sample->status);
        $this->assertNotNull($sample->stock_deducted_at);
        $this->assertSame('AED', $sample->commission_currency);

        // Normal-sale commission for price x qty on an FBN listing: category pct (12%) of 1200 x 2 + fixed (300) x 2.
        $expected = (int) floor(1200 * 2 * 12 / 100) + 300 * 2;
        $this->assertSame(888, $expected);
        $this->assertSame($expected, $sample->commission_amount);
        $this->assertSame($expected, $svc->commissionFor($listing->fresh(), $campaign, 2));
    }

    public function test_a_cancelled_sample_can_not_be_dispatched(): void
    {
        F::categoryQty($this->s, platform: 1, influencer: 1);
        $campaign = F::campaign($this->s, [F::influencer($this->s)]);
        $sample = $campaign->samples()->where('sample_type', 'admin')->sole();
        app(CampaignSampleService::class)->cancel($sample);

        $this->expectException(\RuntimeException::class);
        app(CampaignSampleService::class)->dispatch($sample->fresh());
    }
}
