<?php

namespace Tests\Feature\Campaigns;

use App\Models\Admin;
use App\Models\MarketerCampaignSample;
use App\Services\Inventory\InventoryService;
use App\Services\MarketerCampaignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\Support\CampaignSampleFixtures as F;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/** P8, admin panel: approve -> dispatch sample -> stock deducted once, commission snapshot, samples table. */
class CampaignSamplesAdminTest extends TestCase
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

        $perms = ['vendors.assigned_only', 'marketer_campaigns.view', 'marketer_campaigns.approve', 'marketer_campaigns.reject', 'marketer_campaigns.create'];
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'admin']);
        }
        $this->admin = Admin::factory()->create();
        $this->admin->givePermissionTo(array_slice($perms, 1));
    }

    private function stock(): int
    {
        return app(InventoryService::class)->availableStock($this->s->vendorListingFbn->fresh());
    }

    public function test_admin_approves_then_dispatches_samples_and_stock_is_deducted_once(): void
    {
        F::categoryQty($this->s, platform: 1, influencer: 2);
        $inf = F::influencer($this->s);
        $campaign = F::campaign($this->s, [$inf]);

        // approve through the admin route
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.marketer-campaigns.approve', $campaign->id))
            ->assertSessionHasNoErrors();
        $this->assertSame('active', $campaign->fresh()->status);

        $adminSample = $campaign->samples()->where('sample_type', 'admin')->sole();
        $infSample = $campaign->samples()->where('sample_type', 'influencer')->sole();
        $url = fn ($sample) => route('admin.marketer-campaigns.samples.update', [$campaign->id, $sample->id]);

        // The influencer has not accepted yet: his sample can not go out.
        $before = $this->stock();
        $this->actingAs($this->admin, 'admin')->patch($url($infSample), ['status' => 'dispatched'])->assertStatus(422);
        $this->assertSame($before, $this->stock());
        $this->assertSame('pending', $infSample->fresh()->status);

        // The fixed admin sample has no invitation: dispatch it -> 1 unit out, commission snapshotted.
        $this->actingAs($this->admin, 'admin')->patch($url($adminSample), ['status' => 'dispatched'])->assertSessionHasNoErrors();
        $this->assertSame($before - 1, $this->stock());
        $adminSample->refresh();
        $this->assertNotNull($adminSample->stock_deducted_at);
        $this->assertSame((int) floor(1200 * 12 / 100) + 300, $adminSample->commission_amount);

        // Repeating the request does not take stock or charge twice.
        $this->actingAs($this->admin, 'admin')->patch($url($adminSample), ['status' => 'dispatched'])->assertStatus(422);
        $this->assertSame($before - 1, $this->stock());
        $this->assertSame((int) floor(1200 * 12 / 100) + 300, $adminSample->fresh()->commission_amount);

        // Once the influencer accepts, his 2-unit sample can be dispatched (stock -2, once).
        app(MarketerCampaignService::class)->acceptInvitation($campaign->invitations()->sole());
        $this->actingAs($this->admin, 'admin')->patch($url($infSample), ['status' => 'dispatched'])->assertSessionHasNoErrors();
        $this->assertSame($before - 3, $this->stock());
        $this->assertSame((int) floor(1200 * 2 * 12 / 100) + 300 * 2, $infSample->fresh()->commission_amount);

        // delivered / returned transitions still work; a cancelled sample is final.
        $this->actingAs($this->admin, 'admin')->patch($url($infSample), ['status' => 'delivered'])->assertSessionHasNoErrors();
        $this->assertSame('delivered', $infSample->fresh()->status);
        $this->assertSame($before - 3, $this->stock());
    }

    public function test_cancelled_sample_cannot_be_dispatched_from_the_admin_panel(): void
    {
        F::categoryQty($this->s, platform: 1, influencer: 1);
        $campaign = F::campaign($this->s, [F::influencer($this->s)]);
        $sample = $campaign->samples()->where('sample_type', 'admin')->sole();
        $sample->update(['status' => 'cancelled']);
        $before = $this->stock();

        $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.marketer-campaigns.samples.update', [$campaign->id, $sample->id]), ['status' => 'dispatched'])
            ->assertStatus(422);
        $this->assertSame($before, $this->stock());
    }

    public function test_admin_campaign_page_lists_samples_with_totals_and_waiting_state(): void
    {
        F::categoryQty($this->s, platform: 1, influencer: 2);
        $inf = F::influencer($this->s);
        $campaign = F::campaign($this->s, [$inf]);
        app(MarketerCampaignService::class)->approveCampaign($campaign, $this->admin);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.marketer-campaigns.show', $campaign->id))
            ->assertOk()
            ->assertSee('data-testid="admin-samples-summary"', false)
            ->assertSee(__('admin.marketer_campaigns.samples_summary', ['count' => 2, 'units' => 3, 'dispatched' => 0]))
            ->assertSee(__('admin.marketer_campaigns.sample_awaiting_acceptance'))
            ->assertSee($inf->name);
    }

    public function test_admin_without_approve_permission_cannot_dispatch(): void
    {
        F::categoryQty($this->s, platform: 1);
        $campaign = F::campaign($this->s, [F::influencer($this->s)]);
        $sample = $campaign->samples()->where('sample_type', 'admin')->sole();

        $viewer = Admin::factory()->create();
        $viewer->givePermissionTo('marketer_campaigns.view');

        $this->actingAs($viewer, 'admin')
            ->patch(route('admin.marketer-campaigns.samples.update', [$campaign->id, $sample->id]), ['status' => 'dispatched'])
            ->assertForbidden();
        $this->assertNull(MarketerCampaignSample::find($sample->id)->stock_deducted_at);
    }
}
