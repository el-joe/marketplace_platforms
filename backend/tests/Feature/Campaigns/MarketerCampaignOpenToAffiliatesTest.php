<?php

namespace Tests\Feature\Campaigns;

use App\Models\Admin;
use App\Models\Country;
use App\Models\MarketerCampaign;
use App\Models\MarketerInfluencerFeeCountrySetting;
use App\Models\VendorAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\CampaignSampleFixtures as F;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/** Vendor create form: "Open to all registered affiliates" + decimal platform fee / VAT preview. */
class MarketerCampaignOpenToAffiliatesTest extends TestCase
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
        foreach (['listings.view', 'marketer_campaigns.view', 'marketer_campaigns.create'] as $name) {
            $this->vendorAdmin->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'vendor']));
        }
        Admin::factory()->create();
        F::categoryQty($this->s, platform: 1, influencer: 1, affiliate: 0);
    }

    private function store(array $payload)
    {
        return $this->actingAs($this->vendorAdmin, 'vendor')->post(route('partner.marketer-campaigns.store'), [
            'vendor_listing_id' => $this->s->vendorListingFbn->id,
        ] + $payload);
    }

    public function test_open_to_affiliates_invites_every_eligible_affiliate_and_no_influencer_is_needed(): void
    {
        $a = F::affiliate($this->s);
        $b = F::affiliate($this->s);

        $this->store(['open_to_affiliates' => 1])->assertSessionMissing('error')->assertSessionHasNoErrors()->assertRedirect();

        $campaign = MarketerCampaign::where('vendor_listing_id', $this->s->vendorListingFbn->id)->sole();
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $campaign->requested_marketer_vendor_ids);
    }

    public function test_open_to_affiliates_adds_to_hand_picked_influencers_and_ignores_other_countries(): void
    {
        $influencer = F::influencer($this->s);
        $affiliate = F::affiliate($this->s);
        $foreign = F::affiliate($this->s);
        $foreign->update(['country_id' => Country::factory()->create()->id]);

        $this->store(['open_to_affiliates' => 1, 'marketer_ids' => [$influencer->id]])->assertSessionHasNoErrors();

        $campaign = MarketerCampaign::where('vendor_listing_id', $this->s->vendorListingFbn->id)->sole();
        $this->assertEqualsCanonicalizing([$influencer->id, $affiliate->id], $campaign->requested_marketer_vendor_ids);
    }

    public function test_without_the_checkbox_or_a_pick_nothing_is_created(): void
    {
        F::affiliate($this->s);

        $this->store([])->assertSessionHasErrors('marketer_ids');
        $this->assertSame(0, MarketerCampaign::where('vendor_listing_id', $this->s->vendorListingFbn->id)->count());
    }

    public function test_checkbox_is_enabled_and_counts_affiliates_when_some_exist(): void
    {
        F::affiliate($this->s);

        $this->actingAs($this->vendorAdmin, 'vendor')
            ->get(route('partner.marketer-campaigns.create', $this->s->vendorListingFbn->id))
            ->assertOk()
            ->assertSee(__('partner.marketer_campaigns.open_to_affiliates_desc', ['count' => 1]));
    }

    public function test_fee_and_vat_preview_accepts_a_decimal_fee_and_the_budget_accepts_cents(): void
    {
        MarketerInfluencerFeeCountrySetting::updateOrCreate(
            ['country_id' => $this->s->country->id],
            ['fee_per_influencer' => 2, 'currency' => 'AED', 'updated_by_admin_id' => null]
        );
        $inf = F::influencer($this->s);

        $quote = $this->actingAs($this->vendorAdmin, 'vendor')
            ->getJson(route('partner.listings.marketer-fee', ['listing_id' => $this->s->vendorListingFbn->id, 'marketer_ids' => [$inf->id]]))
            ->assertOk()->json();
        $this->assertEqualsWithDelta(0.1, $quote['fee_vat_per_influencer'], 0.00001);
        $this->assertEqualsWithDelta(2.1, $quote['total_fee_with_vat'], 0.00001);

        $this->store(['marketer_ids' => [$inf->id], 'max_commission_budget' => '1250.75'])->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(1250.75, (float) MarketerCampaign::where('vendor_listing_id', $this->s->vendorListingFbn->id)->sole()->max_commission_budget, 0.00001);

        $this->store(['marketer_ids' => [$inf->id], 'max_commission_budget' => '10.123'])->assertSessionHasErrors('max_commission_budget');
    }

    public function test_checkbox_is_enabled_without_affiliates_and_create_form_shows_wallet_balance(): void
    {
        $html = $this->actingAs($this->vendorAdmin, 'vendor')
            ->get(route('partner.marketer-campaigns.create', $this->s->vendorListingFbn->id))
            ->assertOk()->getContent();

        $this->assertDoesNotMatchRegularExpression('/name="open_to_affiliates"[^>]*disabled/', $html);
        $this->assertStringContainsString('data-testid="wallet-balance"', $html);
    }

    public function test_admin_campaign_page_lists_requested_marketers_before_approval(): void
    {
        $influencer = F::influencer($this->s);
        $this->store(['marketer_ids' => [$influencer->id]])->assertSessionHasNoErrors();
        $campaign = MarketerCampaign::where('vendor_listing_id', $this->s->vendorListingFbn->id)->sole();
        $this->assertSame([$influencer->id], $campaign->requested_marketer_vendor_ids);

        Permission::firstOrCreate(['name' => 'vendors.assigned_only', 'guard_name' => 'admin']);
        Permission::firstOrCreate(['name' => 'marketer_campaigns.view', 'guard_name' => 'admin']);
        $admin = Admin::first();
        $admin->givePermissionTo('marketer_campaigns.view');
        $this->actingAs($admin, 'admin')
            ->get(route('admin.marketer-campaigns.show', $campaign->id))
            ->assertOk()
            ->assertSee('data-testid="requested-marketers"', false)
            ->assertSee($influencer->name);
    }
}
