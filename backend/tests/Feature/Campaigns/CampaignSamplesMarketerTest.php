<?php

namespace Tests\Feature\Campaigns;

use App\Models\Admin;
use App\Models\MarketerCampaignSample;
use App\Services\MarketerCampaignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\Support\CampaignSampleFixtures as F;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/** P8, marketer panel: the samples page lists the influencer's own sample only. */
class CampaignSamplesMarketerTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $s;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Notification::fake();
        $this->withoutVite();
        $this->s = MarketplaceScenario::make()->build();
        Admin::factory()->create();
    }

    public function test_samples_page_lists_only_the_accepted_influencers_own_sample(): void
    {
        F::categoryQty($this->s, platform: 1, influencer: 3);
        $mine = F::influencer($this->s);
        $other = F::influencer($this->s);
        $campaign = F::campaign($this->s, [$mine, $other]);
        $svc = app(MarketerCampaignService::class);
        $svc->approveCampaign($campaign, Admin::factory()->create());

        $login = F::marketerLogin($mine);

        // Invitation still pending: nothing to show (and nothing to act on).
        $this->actingAs($login, 'marketer')->get(route('marketer.samples.index'))
            ->assertOk()
            ->assertSee(__('marketer.samples.no_samples'));

        $svc->acceptInvitation($campaign->invitations()->where('marketer_id', $mine->id)->sole());
        $svc->acceptInvitation($campaign->invitations()->where('marketer_id', $other->id)->sole());

        $ownSample = MarketerCampaignSample::whereHas('invitation', fn ($q) => $q->where('marketer_id', $mine->id))->sole();
        $otherSample = MarketerCampaignSample::whereHas('invitation', fn ($q) => $q->where('marketer_id', $other->id))->sole();

        $this->actingAs($login, 'marketer')->get(route('marketer.samples.index'))
            ->assertOk()
            ->assertSee(route('marketer.samples.address', $ownSample->id), false)
            ->assertDontSee(route('marketer.samples.address', $otherSample->id), false)
            ->assertSee(__('marketer.samples.quantity_label').' 3');

        // The platform's fixed admin sample is never shown to a marketer.
        $adminSample = $campaign->samples()->where('sample_type', 'admin')->sole();
        $this->actingAs($login, 'marketer')->get(route('marketer.samples.index'))
            ->assertDontSee(route('marketer.samples.address', $adminSample->id), false);
    }

    public function test_cancelled_sample_disappears_and_cannot_receive_an_address(): void
    {
        F::categoryQty($this->s, platform: 1, influencer: 1);
        $mine = F::influencer($this->s);
        $campaign = F::campaign($this->s, [$mine]);
        $svc = app(MarketerCampaignService::class);
        $svc->approveCampaign($campaign, Admin::factory()->create());
        $svc->acceptInvitation($campaign->invitations()->sole());
        $sample = MarketerCampaignSample::where('sample_type', 'influencer')->sole();

        $svc->cancelCampaign($campaign->fresh());
        $this->assertSame('cancelled', $sample->fresh()->status);

        $login = F::marketerLogin($mine);
        $this->actingAs($login, 'marketer')->get(route('marketer.samples.index'))
            ->assertOk()
            ->assertDontSee(route('marketer.samples.address', $sample->id), false);

        $this->actingAs($login, 'marketer')
            ->post(route('marketer.samples.address', $sample->id), ['address_line_1' => 'x', 'city' => 'c', 'country' => 'AE', 'phone' => '1'])
            ->assertStatus(422);
    }
}
