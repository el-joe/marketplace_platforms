<?php

namespace Tests\Feature\Campaigns;

use App\Exceptions\PeerPromotionException;
use App\Models\Admin;
use App\Models\Setting;
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

    // ───────── marketer_peer_campaign_min_stock in the admin marketer settings ─────────

    private function settingsAdmin(bool $canEdit = true): Admin
    {
        foreach (['marketer_commission_settings.view', 'marketer_fee_settings.edit'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'admin']);
        }
        $admin = Admin::factory()->create();
        $admin->givePermissionTo($canEdit ? ['marketer_commission_settings.view', 'marketer_fee_settings.edit'] : ['marketer_commission_settings.view']);

        return $admin;
    }

    public function test_settings_page_shows_and_saves_the_peer_min_stock_in_both_languages(): void
    {
        $admin = $this->settingsAdmin();

        foreach (['en', 'ar'] as $locale) {
            $this->withSession(['locale' => $locale])->actingAs($admin, 'admin')
                ->get(route('admin.marketer-settings.index'))
                ->assertOk()
                ->assertSee('data-peer-min-stock', false)
                ->assertSee('name="marketer_peer_campaign_min_stock"', false)
                ->assertSee(__('admin.marketer_settings.peer_min_stock_title'));
        }

        $this->actingAs($admin, 'admin')
            ->post(route('admin.marketer-settings.update-peer-min-stock'), ['marketer_peer_campaign_min_stock' => 12])
            ->assertRedirect()->assertSessionHas('success');
        Setting::flush();
        $this->assertSame(12, (int) setting('marketer_peer_campaign_min_stock'));

        $this->actingAs($admin, 'admin')->get(route('admin.marketer-settings.index'))->assertSee('value="12"', false);
    }

    public function test_saved_peer_min_stock_is_enforced_by_the_promotion_request(): void
    {
        $admin = $this->settingsAdmin();
        $this->actingAs($admin, 'admin')
            ->post(route('admin.marketer-settings.update-peer-min-stock'), ['marketer_peer_campaign_min_stock' => 15]);

        $owner = F::influencer($this->s);
        $promoter = F::influencer($this->s);
        $this->expectException(PeerPromotionException::class);
        P::request($promoter, P::ownListing($this->s, $owner, 14));
    }

    public function test_peer_min_stock_is_validated_and_permission_protected(): void
    {
        $this->actingAs($this->settingsAdmin(), 'admin');
        foreach ([0, -3, 'abc', '', 1.5] as $bad) {
            $this->post(route('admin.marketer-settings.update-peer-min-stock'), ['marketer_peer_campaign_min_stock' => $bad])
                ->assertSessionHasErrors('marketer_peer_campaign_min_stock');
        }

        $this->actingAs($this->settingsAdmin(canEdit: false), 'admin')
            ->post(route('admin.marketer-settings.update-peer-min-stock'), ['marketer_peer_campaign_min_stock' => 5])
            ->assertForbidden();
        $this->assertSame(1, (int) setting('marketer_peer_campaign_min_stock', 1));
    }
}
