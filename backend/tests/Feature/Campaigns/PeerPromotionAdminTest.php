<?php

namespace Tests\Feature\Campaigns;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\Support\CampaignSampleFixtures as F;
use Tests\Support\MarketplaceScenario;
use Tests\Support\PeerPromotionFixtures as P;
use Tests\TestCase;

/** P13 (R11), admin panel: the campaign list and page show source, owner influencer and promoter. */
class PeerPromotionAdminTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $s;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Notification::fake();
        $this->withoutVite();
        $this->s = MarketplaceScenario::make()->build();

        foreach (['vendors.assigned_only', 'marketer_campaigns.view'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'admin']);
        }
        $this->admin = Admin::factory()->create();
        $this->admin->givePermissionTo('marketer_campaigns.view');
    }

    public function test_campaign_page_shows_source_owner_and_promoter(): void
    {
        $owner = F::influencer($this->s);
        $promoter = F::influencer($this->s);
        $campaign = P::request($promoter, P::ownListing($this->s, $owner));

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.marketer-campaigns.show', $campaign))
            ->assertOk()
            ->assertSee(__('admin.marketer_campaigns.source_peer'))
            ->assertSee($owner->name)
            ->assertSee($promoter->name)
            ->assertSee(__('admin.marketer_campaigns.peer_promoter'));
    }

    public function test_campaign_list_shows_owner_and_filters_by_source(): void
    {
        $owner = F::influencer($this->s);
        $promoter = F::influencer($this->s);
        $campaign = P::request($promoter, P::ownListing($this->s, $owner));

        $peer = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.marketer-campaigns.index', ['source' => 'peer']))
            ->assertOk()
            ->assertSee($owner->name)
            ->assertSee(route('admin.marketer-campaigns.show', $campaign), false);
        $this->assertSame(1, substr_count($peer->getContent(), '/marketer-campaigns/'.$campaign->id));

        // The vendor scenario campaign is filtered out of the peer view and vice versa.
        $this->get(route('admin.marketer-campaigns.index', ['source' => 'peer']))
            ->assertDontSee(route('admin.marketer-campaigns.show', $this->s->marketerCampaign), false);
        $this->get(route('admin.marketer-campaigns.index', ['source' => 'vendor']))
            ->assertSee(route('admin.marketer-campaigns.show', $this->s->marketerCampaign), false)
            ->assertDontSee(route('admin.marketer-campaigns.show', $campaign), false);
    }

    public function test_admin_pages_have_arabic_labels(): void
    {
        app()->setLocale('ar');
        foreach (['source_column', 'all_sources', 'source_vendor', 'source_admin', 'source_peer', 'peer_owner', 'peer_promoter'] as $key) {
            $this->assertNotSame('admin.marketer_campaigns.'.$key, __('admin.marketer_campaigns.'.$key));
        }
    }
}
