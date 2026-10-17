<?php

namespace Tests\Feature\Campaigns;

use App\Enums\ClassifiedListingStatus;
use App\Models\MarketerCampaign;
use App\Models\MarketerCampaignConversion;
use App\Models\VendorAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\CampaignSampleFixtures as F;
use Tests\Support\MarketerV3Fixtures as V;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/** Partner panel (classified vendor): campaign wizard access, creation and the Mark sold endpoint. */
class ClassifiedVendorCampaignWizardTest extends TestCase
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
        V::classifiedVendor($this->s);

        $this->vendorAdmin = VendorAdmin::create([
            'vendor_id' => $this->s->vendor->id, 'name' => 'Owner', 'email' => 'va-'.Str::lower(Str::random(8)).'@example.test',
            'password' => bcrypt('password'), 'role' => 'owner', 'is_owner' => true, 'is_active' => true,
        ]);
        foreach (['listings.view', 'marketer_campaigns.view', 'marketer_campaigns.create', 'marketer_campaigns.cancel'] as $name) {
            $this->vendorAdmin->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'vendor']));
        }
    }

    public function test_a_classified_vendor_opens_the_wizard_for_its_active_listing(): void
    {
        $listing = V::classifiedListing($this->s);
        F::affiliate($this->s);

        $this->actingAs($this->vendorAdmin, 'vendor')
            ->get(route('partner.marketer-campaigns.create-classified', $listing->id))
            ->assertOk();
        $this->actingAs($this->vendorAdmin, 'vendor')->get(route('partner.marketer-campaigns.index'))->assertOk();
    }

    public function test_the_wizard_refuses_another_sellers_listing_and_a_listing_that_is_not_active(): void
    {
        $foreign = V::classifiedListing($this->s, ['seller_id' => (string) Str::uuid()]);
        $paused = V::classifiedListing($this->s, ['status' => 'paused']);

        $this->actingAs($this->vendorAdmin, 'vendor')->get(route('partner.marketer-campaigns.create-classified', $foreign->id))->assertForbidden();
        $this->actingAs($this->vendorAdmin, 'vendor')->get(route('partner.marketer-campaigns.create-classified', $paused->id))->assertForbidden();
    }

    public function test_the_vendor_creates_a_classified_campaign_open_to_all_affiliates_and_brokers(): void
    {
        $listing = V::classifiedListing($this->s);
        $affiliate = F::affiliate($this->s);
        $broker = V::broker($this->s);

        $this->actingAs($this->vendorAdmin, 'vendor')->post(route('partner.marketer-campaigns.store'), [
            'classified_listing_id' => $listing->id, 'max_commission_budget' => 800, 'open_to_affiliates' => 1,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $campaign = MarketerCampaign::where('classified_listing_id', $listing->id)->sole();
        $this->assertSame('classified', $campaign->campaign_category);
        $this->assertEquals(800, $campaign->max_commission_budget);
        $this->assertEqualsCanonicalizing([$affiliate->id, $broker->id], $campaign->requested_marketer_vendor_ids);
        $this->assertTrue((bool) $listing->fresh()->campaign_enabled);
    }

    public function test_an_over_budget_influencer_is_rejected(): void
    {
        V::defaults($this->s->country, 'classified', influencerFixed: 500);
        $listing = V::classifiedListing($this->s);
        $influencer = F::influencer($this->s);

        $this->actingAs($this->vendorAdmin, 'vendor')->post(route('partner.marketer-campaigns.store'), [
            'classified_listing_id' => $listing->id, 'max_commission_budget' => 100, 'marketer_ids' => [$influencer->id],
        ])->assertSessionHasErrors('max_commission_budget');

        $this->assertSame(0, MarketerCampaign::where('classified_listing_id', $listing->id)->count());
    }

    public function test_the_classified_budget_is_optional_with_an_end_date_and_must_exceed_the_influencers(): void
    {
        V::defaults($this->s->country, 'classified', influencerFixed: 500);
        $listing = V::classifiedListing($this->s);
        $influencer = F::influencer($this->s);
        $this->actingAs($this->vendorAdmin, 'vendor');
        $payload = ['classified_listing_id' => $listing->id, 'marketer_ids' => [$influencer->id]];

        $this->post(route('partner.marketer-campaigns.store'), $payload + ['max_commission_budget' => 500])->assertSessionHasErrors('max_commission_budget');
        $this->post(route('partner.marketer-campaigns.store'), $payload)->assertSessionHasErrors('ends_at');
        $this->assertSame(0, MarketerCampaign::where('classified_listing_id', $listing->id)->count());

        $this->post(route('partner.marketer-campaigns.store'), $payload + ['ends_at' => now()->addWeek()->toDateString()])->assertSessionHasNoErrors();
        $campaign = MarketerCampaign::where('classified_listing_id', $listing->id)->sole();
        $this->assertEquals(0, $campaign->max_commission_budget);
        $this->assertEquals(500, $campaign->campaign_subtotal);
        $this->assertNull($campaign->budgetRemaining());
    }

    public function test_mark_sold_endpoint_requires_a_price_when_a_campaign_is_live_then_records_the_sale(): void
    {
        $listing = V::classifiedListing($this->s);
        $affiliate = F::affiliate($this->s);
        V::rule($affiliate, 'classified', rate: 10);
        V::classifiedCampaign($this->s, $listing, [$affiliate], 1000);
        $this->actingAs($this->vendorAdmin, 'vendor');

        $this->postJson(route('partner.classifieds.mark-sold', $listing->id), ['marketer_id' => $affiliate->id])
            ->assertStatus(422)->assertJsonValidationErrors('sold_price');
        $this->assertSame(ClassifiedListingStatus::Active, $listing->fresh()->status);

        $this->postJson(route('partner.classifieds.mark-sold', $listing->id), ['sold_price' => 4000, 'marketer_id' => $affiliate->id])
            ->assertOk()->assertJsonPath('success', true);

        $this->assertSame(ClassifiedListingStatus::Sold, $listing->fresh()->status);
        $this->assertEquals(400, MarketerCampaignConversion::where('classified_listing_id', $listing->id)->sole()->gross_commission_amount);

        $this->postJson(route('partner.classifieds.unmark-sold', $listing->id))->assertOk();
        $this->assertSame('reversed', MarketerCampaignConversion::where('classified_listing_id', $listing->id)->sole()->status);
    }
}
