<?php

namespace Tests\Feature\Travel;

use App\Models\MarketerCampaign;
use App\Models\TravelAgency;
use App\Models\TravelAgencyMember;
use App\Models\TravelPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\Support\CampaignSampleFixtures as F;
use Tests\Support\MarketerV3Fixtures as V;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/** Travel agency "Campaigns" section: access, permissions, ownership isolation and the create / quote / manage flow. */
class TravelAgencyCampaignPagesTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $s;

    private TravelAgency $agency;

    private TravelAgencyMember $owner;

    private TravelPackage $package;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Notification::fake();
        $this->withoutVite();
        $this->s = MarketplaceScenario::make()->build();
        $this->agency = V::agency($this->s);
        $this->owner = V::agencyMember($this->agency);
        $this->package = V::package($this->agency);
    }

    private function staffWith(array $permissions): TravelAgencyMember
    {
        $staff = V::agencyMember($this->agency, owner: false);
        foreach ($permissions as $name) {
            $staff->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'travel_agency']));
        }

        return $staff;
    }

    private function campaign(): MarketerCampaign
    {
        return V::travelCampaign($this->s, $this->agency, $this->package, [F::affiliate($this->s)], 500);
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get(route('travel-agency.campaigns.index'))->assertRedirect(route('travel-agency.login'));
    }

    public function test_the_owner_can_open_every_campaign_page(): void
    {
        $campaign = $this->campaign();
        $this->actingAs($this->owner, 'travel_agency');

        $this->get(route('travel-agency.campaigns.index'))->assertOk();
        $this->get(route('travel-agency.campaigns.create'))->assertOk();
        $this->get(route('travel-agency.campaigns.create-trip', $this->package->id))->assertOk();
        $this->get(route('travel-agency.campaigns.show', $campaign->id))->assertOk();
    }

    public function test_a_member_without_the_permission_is_turned_away_and_with_it_gets_in(): void
    {
        $this->actingAs($this->staffWith([]), 'travel_agency')
            ->get(route('travel-agency.campaigns.index'))->assertRedirect()->assertSessionHas('error');

        $this->actingAs($this->staffWith(['campaigns.view']), 'travel_agency')
            ->get(route('travel-agency.campaigns.index'))->assertOk();
    }

    public function test_viewing_does_not_allow_creating_or_managing(): void
    {
        $campaign = $this->campaign();
        $viewer = $this->staffWith(['campaigns.view']);

        $this->actingAs($viewer, 'travel_agency')->get(route('travel-agency.campaigns.create'))->assertRedirect()->assertSessionHas('error');
        $this->actingAs($viewer, 'travel_agency')->post(route('travel-agency.campaigns.pause', $campaign->id))->assertSessionHas('error');
        $this->assertSame('active', $campaign->fresh()->status);

        $manager = $this->staffWith(['campaigns.view', 'campaigns.manage']);
        $this->actingAs($manager, 'travel_agency')->post(route('travel-agency.campaigns.pause', $campaign->id))->assertSessionHas('success');
        $this->assertNotNull($campaign->fresh()->owner_paused_at);
    }

    public function test_an_agency_cannot_see_or_promote_another_agencys_items(): void
    {
        $campaign = $this->campaign();
        $otherAgency = V::agency($this->s);
        $other = V::agencyMember($otherAgency);

        $this->actingAs($other, 'travel_agency')->get(route('travel-agency.campaigns.show', $campaign->id))->assertNotFound();
        $this->actingAs($other, 'travel_agency')->get(route('travel-agency.campaigns.create-trip', $this->package->id))->assertNotFound();
        $this->actingAs($other, 'travel_agency')->post(route('travel-agency.campaigns.store'), [
            'travel_package_id' => $this->package->id, 'max_commission_budget' => 100, 'open_to_affiliates' => 1,
        ])->assertNotFound();
    }

    public function test_the_quote_shows_the_influencer_total_and_whether_it_fits_the_budget(): void
    {
        V::defaults($this->s->country, 'travel', influencerFixed: 150);
        $influencer = F::influencer($this->s);
        $this->actingAs($this->owner, 'travel_agency');

        $this->getJson(route('travel-agency.campaigns.quote', ['trip_id' => $this->package->id, 'marketer_ids' => [$influencer->id], 'budget' => 100]))
            ->assertOk()->assertJsonPath('influencer_fixed_total', 150)->assertJsonPath('fits_budget', false);

        $this->getJson(route('travel-agency.campaigns.quote', ['trip_id' => $this->package->id, 'marketer_ids' => [$influencer->id], 'budget' => 500]))
            ->assertOk()->assertJsonPath('fits_budget', true);
    }

    public function test_the_agency_creates_a_campaign_for_its_trip_and_it_reserves_the_influencer_amount(): void
    {
        V::defaults($this->s->country, 'travel', influencerFixed: 150);
        $influencer = F::influencer($this->s);

        $response = $this->actingAs($this->owner, 'travel_agency')->post(route('travel-agency.campaigns.store'), [
            'travel_package_id' => $this->package->id, 'marketer_ids' => [$influencer->id], 'max_commission_budget' => '500.50',
        ]);

        $campaign = MarketerCampaign::where('travel_package_id', $this->package->id)->sole();
        $response->assertRedirect(route('travel-agency.campaigns.show', $campaign->id));
        $this->assertSame(MarketerCampaign::OWNER_TRAVEL_AGENCY, $campaign->owner_type);
        $this->assertSame($this->agency->id, (string) $campaign->owner_id);
        $this->assertNull($campaign->vendor_id);
        $this->assertEquals(150, $campaign->influencer_fixed_reserved);
        $this->assertEquals(500.5, $campaign->max_commission_budget);
    }

    public function test_an_over_budget_pick_is_refused_and_nothing_is_created(): void
    {
        V::defaults($this->s->country, 'travel', influencerFixed: 150);
        $influencer = F::influencer($this->s);

        $this->actingAs($this->owner, 'travel_agency')->post(route('travel-agency.campaigns.store'), [
            'travel_package_id' => $this->package->id, 'marketer_ids' => [$influencer->id], 'max_commission_budget' => 100,
        ])->assertSessionHasErrors('max_commission_budget');

        $this->assertSame(0, MarketerCampaign::where('travel_package_id', $this->package->id)->count());
    }

    public function test_cancelling_releases_the_reservation(): void
    {
        V::defaults($this->s->country, 'travel', influencerFixed: 150);
        $influencer = F::influencer($this->s);
        $campaign = V::travelCampaign($this->s, $this->agency, $this->package, [$influencer], 500);
        $this->assertEquals(150, $campaign->influencer_fixed_reserved);

        $this->actingAs($this->owner, 'travel_agency')->post(route('travel-agency.campaigns.cancel', $campaign->id));

        $this->assertSame('cancelled', $campaign->fresh()->status);
        $this->assertEquals(0, $campaign->fresh()->influencer_fixed_reserved);
    }
}
