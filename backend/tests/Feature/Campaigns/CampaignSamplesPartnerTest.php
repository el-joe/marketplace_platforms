<?php

namespace Tests\Feature\Campaigns;

use App\Models\Admin;
use App\Models\MarketerCampaign;
use App\Models\Vendor;
use App\Models\VendorAdmin;
use App\Services\MarketerCampaignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\CampaignSampleFixtures as F;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/** P8, vendor (partner) panel: sample plan on create, samples table + totals on the campaign page. */
class CampaignSamplesPartnerTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $s;

    private VendorAdmin $vendorAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Notification::fake();
        $this->withoutVite();
        $this->s = MarketplaceScenario::make()->build();

        $this->vendorAdmin = VendorAdmin::create([
            'vendor_id' => $this->s->vendor->id, 'name' => 'Owner', 'email' => 'va-'.Str::lower(Str::random(8)).'@example.test',
            'password' => bcrypt('password'), 'role' => 'owner', 'is_owner' => true, 'is_active' => true,
        ]);
        foreach (['listings.view', 'marketer_campaigns.view', 'marketer_campaigns.create', 'marketer_campaigns.cancel', 'marketer_campaigns.edit', 'marketer_campaigns.delete'] as $name) {
            $this->vendorAdmin->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'vendor']));
        }
        Admin::factory()->create();
    }

    public function test_create_page_shows_the_sample_plan_with_the_fixed_sample(): void
    {
        F::categoryQty($this->s, platform: 2, influencer: 3, affiliate: 4);
        F::influencer($this->s);

        $this->actingAs($this->vendorAdmin, 'vendor')
            ->get(route('partner.marketer-campaigns.create', $this->s->vendorListingFbn->id))
            ->assertOk()
            ->assertSee('data-testid="sample-plan"', false)
            ->assertSee(__('partner.marketer_campaigns.sample_breakdown_admin'))
            ->assertSee(__('partner.marketer_campaigns.stock_after_fixed_sample', ['fixed' => 2, 'after' => 38]));
    }

    public function test_create_is_blocked_when_stock_left_after_the_fixed_sample_is_below_the_minimum(): void
    {
        F::categoryQty($this->s, platform: 1, influencer: 1, minStock: 10);
        $this->s->vendorListingFbnInventory->update(['quantity_on_hand' => 10]); // 10 - 1 fixed sample = 9 < 10
        $inf = F::influencer($this->s);

        $this->actingAs($this->vendorAdmin, 'vendor')
            ->get(route('partner.marketer-campaigns.create', $this->s->vendorListingFbn->id))
            ->assertOk()
            ->assertSee(__('partner.marketer_campaigns.stock_blocked'));

        $this->actingAs($this->vendorAdmin, 'vendor')
            ->post(route('partner.marketer-campaigns.store'), [
                'vendor_listing_id' => $this->s->vendorListingFbn->id,
                'marketer_ids' => [$inf->id],
            ])
            ->assertSessionHas('error');
        $this->assertSame(0, MarketerCampaign::where('vendor_listing_id', $this->s->vendorListingFbn->id)->count(), 'blocked: no campaign created');
    }

    public function test_campaign_page_lists_the_admin_sample_then_one_sample_per_influencer(): void
    {
        F::categoryQty($this->s, platform: 1, influencer: 2);
        $a = F::influencer($this->s);
        $b = F::influencer($this->s);

        $this->actingAs($this->vendorAdmin, 'vendor')
            ->post(route('partner.marketer-campaigns.store'), [
                'vendor_listing_id' => $this->s->vendorListingFbn->id,
                'marketer_ids' => [$a->id, $b->id],
            ])
            ->assertSessionMissing('error')
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $campaign = MarketerCampaign::where('vendor_listing_id', $this->s->vendorListingFbn->id)->sole();
        $show = route('partner.marketer-campaigns.show', $campaign->id);

        // Right after creation: only the fixed platform sample (invitations follow admin approval).
        $this->actingAs($this->vendorAdmin, 'vendor')->get($show)->assertOk()
            ->assertSee(__('partner.marketer_campaigns_my.samples_summary', ['count' => 1, 'units' => 1, 'dispatched' => 0]))
            ->assertSee(__('partner.marketer_campaigns_my.sample_type.admin'));

        // Approval dispatches the invitations -> 3 samples, 5 units, each influencer listed.
        $campaign->update(['marketer_commission_amount' => 5000]);
        app(MarketerCampaignService::class)->approveCampaign($campaign->fresh(), Admin::factory()->create());

        $this->actingAs($this->vendorAdmin, 'vendor')->get($show)->assertOk()
            ->assertSee(__('partner.marketer_campaigns_my.samples_summary', ['count' => 3, 'units' => 5, 'dispatched' => 0]))
            ->assertSee($a->name)
            ->assertSee($b->name)
            ->assertSee(__('partner.marketer_campaigns_my.sample_status.pending'));
    }

    public function test_vendor_cannot_see_another_vendors_campaign_samples(): void
    {
        F::categoryQty($this->s, platform: 1, influencer: 1);
        $campaign = F::campaign($this->s, [F::influencer($this->s)]);

        $otherVendor = Vendor::create([
            'name' => 'Other', 'email' => 'o-'.Str::lower(Str::random(8)).'@example.test', 'phone' => '+9715'.fake()->numerify('########'),
            'password' => bcrypt('password'), 'store_name' => 'Other '.Str::random(4), 'store_slug' => 'other-'.Str::lower(Str::random(8)),
            'business_type' => 'llc', 'payout_schedule' => 'monthly', 'global_status' => 'active',
            'country_id' => $this->s->country->id, 'approved_at' => now(),
        ]);
        $intruder = VendorAdmin::create([
            'vendor_id' => $otherVendor->id, 'name' => 'X', 'email' => 'x-'.Str::lower(Str::random(8)).'@example.test',
            'password' => bcrypt('password'), 'role' => 'owner', 'is_owner' => true, 'is_active' => true,
        ]);
        $intruder->givePermissionTo(Permission::firstOrCreate(['name' => 'marketer_campaigns.view', 'guard_name' => 'vendor']));

        $this->actingAs($intruder, 'vendor')
            ->get(route('partner.marketer-campaigns.show', $campaign->id))
            ->assertForbidden();
    }
}
