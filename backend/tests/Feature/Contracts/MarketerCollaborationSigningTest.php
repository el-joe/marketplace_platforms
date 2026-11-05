<?php

namespace Tests\Feature\Contracts;

use App\Models\Activity;
use App\Models\MarketerCampaign;
use App\Models\MarketerSellerContract;
use App\Models\VendorAdmin;
use App\Models\VendorContract;
use App\Services\Marketer\MarketerCollaborationContractService;
use App\Services\MarketerCampaignService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\CampaignSampleFixtures as F;
use Tests\Support\CollaborationContractFixtures;
use Tests\Support\CountryContractFixtures;
use Tests\Support\MarketerV3Fixtures as V;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * The seller signs the applicable collaboration contract(s) before the collaboration with a marketer is created.
 */
class MarketerCollaborationSigningTest extends TestCase
{
    use CollaborationContractFixtures;
    use CountryContractFixtures;
    use RefreshDatabase;

    private MarketplaceScenario $s;

    private VendorAdmin $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);
        Queue::fake();
        Notification::fake();

        $this->s = MarketplaceScenario::make()->build();
        V::classifiedVendor($this->s);
        V::defaults($this->s->country, 'classified', influencerFixed: 100);

        $this->owner = VendorAdmin::create([
            'vendor_id' => $this->s->vendor->id, 'name' => 'Owner', 'email' => 'va-'.Str::lower(Str::random(8)).'@example.test',
            'password' => bcrypt('password'), 'role' => 'owner', 'is_owner' => true, 'is_active' => true,
        ]);
        foreach (['listings.view', 'marketer_campaigns.view', 'marketer_campaigns.create'] as $name) {
            $this->owner->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'vendor']));
        }
    }

    private function submit(array $payload)
    {
        return $this->actingAs($this->owner, 'vendor')->post(route('partner.marketer-campaigns.store'), $payload);
    }

    /**
     * Campaigns created for a listing (the scenario already owns one campaign of its own).
     */
    private function campaignsOf($listing)
    {
        return MarketerCampaign::where('classified_listing_id', $listing->id);
    }

    private function service(): MarketerCollaborationContractService
    {
        return app(MarketerCollaborationContractService::class);
    }

    public function test_a_collaboration_without_a_configured_contract_goes_ahead_unchanged(): void
    {
        $listing = V::classifiedListing($this->s);
        $influencer = F::influencer($this->s);
        $this->collaborationTemplate('Broker only', ['broker_profile']);

        $this->submit(['classified_listing_id' => $listing->id, 'max_commission_budget' => 500, 'marketer_ids' => [$influencer->id]])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $this->campaignsOf($listing)->count());
        $this->assertSame(0, MarketerSellerContract::count());
    }

    public function test_the_seller_must_accept_the_contract_before_the_campaign_is_created(): void
    {
        $listing = V::classifiedListing($this->s);
        $influencer = F::influencer($this->s);
        $template = $this->collaborationTemplate('Influencer deal', ['influencer_profile']);
        $payload = ['classified_listing_id' => $listing->id, 'max_commission_budget' => 500, 'marketer_ids' => [$influencer->id]];

        $this->submit($payload)->assertSessionHasErrors('collaboration_contracts');
        $this->assertSame(0, $this->campaignsOf($listing)->count());

        $this->submit($payload + ['collab_contracts' => [$template->id]])->assertSessionHasErrors('collab_signer_name');
        $this->assertSame(0, $this->campaignsOf($listing)->count());

        // A client-named id of another (unrelated) template does not count.
        $other = $this->collaborationTemplate('Unrelated', ['broker_profile']);
        $this->submit($payload + ['collab_contracts' => [$other->id], 'collab_signer_name' => 'Owner'])->assertSessionHasErrors('collaboration_contracts');
        $this->assertSame(0, $this->campaignsOf($listing)->count());
        $this->assertSame(0, MarketerSellerContract::count());

        $this->submit($payload + ['collab_contracts' => [$template->id], 'collab_signer_name' => 'Owner Name'])->assertSessionHasNoErrors();

        $campaign = $this->campaignsOf($listing)->sole();
        $signed = MarketerSellerContract::sole();
        $this->assertSame($campaign->id, $signed->marketer_campaign_id);
        $this->assertSame($influencer->id, $signed->marketer_id);
        $this->assertSame($this->s->vendor->id, $signed->vendor_id);
        $this->assertSame($template->id, $signed->contract_template_id);
        $this->assertSame(1, $signed->template_version);
        $this->assertSame('Owner Name', $signed->signer_name);
        $this->assertSame($this->owner->id, $signed->signed_by_vendor_admin_id);
        $this->assertSame($this->s->country->id, $signed->country_id);
        $this->assertStringContainsString($influencer->name, $signed->rendered_content);
        $this->assertStringContainsString($this->s->vendor->store_name, $signed->rendered_content);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $signed->rendered_content_hash);
        $this->assertNotNull($signed->signed_ip);
        $this->assertTrue(Activity::where('log_name', 'contracts')->where('event', 'marketer_contract.signed')->exists());
        $this->assertSame(0, VendorContract::count(), 'a collaboration contract is not a seller-platform contract');
    }

    public function test_the_quote_endpoint_lists_the_contracts_the_wizard_must_show(): void
    {
        $listing = V::classifiedListing($this->s);
        $influencer = F::influencer($this->s);
        $template = $this->collaborationTemplate('Influencer deal', ['influencer_profile']);

        $response = $this->actingAs($this->owner, 'vendor')->getJson(route('partner.marketer-campaigns.quote', [
            'classified_id' => $listing->id, 'marketer_ids' => [$influencer->id],
        ]))->assertOk();

        $this->assertSame([$template->id], collect($response->json('agreements'))->pluck('template_id')->all());
        $this->assertSame([$influencer->name], $response->json('agreements.0.marketers'));

        $none = $this->actingAs($this->owner, 'vendor')->getJson(route('partner.marketer-campaigns.quote', ['classified_id' => $listing->id]))->assertOk();
        $this->assertSame([], $none->json('agreements'));
    }

    public function test_a_sellers_country_decides_which_contract_it_signs(): void
    {
        $kuwait = $this->otherCountry();
        $influencer = F::influencer($this->s);
        $own = $this->collaborationTemplate('Deal', ['influencer_profile'], $this->s->country);
        $kuwaitOnly = $this->collaborationTemplate('Kuwait deal', ['influencer_profile'], $kuwait);

        $required = $this->service()->requirementsFor($this->s->vendor, [$influencer->id]);

        $this->assertSame([$own->id], $required->keys()->all());
        $this->assertNotContains($kuwaitOnly->id, $required->keys()->all());

        $kuwaitSeller = $this->vendorIn($kuwait);
        $this->assertSame([$kuwaitOnly->id], $this->service()->requirementsFor($kuwaitSeller, [$influencer->id])->keys()->all());
    }

    public function test_signing_twice_never_duplicates_the_record(): void
    {
        $influencer = F::influencer($this->s);
        $template = $this->collaborationTemplate('Deal', ['influencer_profile']);
        $campaign = V::classifiedCampaign($this->s, V::classifiedListing($this->s), [$influencer]);
        $required = $this->service()->requirementsFor($this->s->vendor, [$influencer->id]);

        $first = $this->service()->sign($this->s->vendor, $this->owner, $campaign, $required, 'Owner', 'en', '127.0.0.1', 'phpunit');
        $second = $this->service()->sign($this->s->vendor, $this->owner, $campaign, $required, 'Owner', 'en', '127.0.0.1', 'phpunit');

        $this->assertCount(1, $first);
        $this->assertCount(0, $second);
        $this->assertSame(1, MarketerSellerContract::where('contract_template_id', $template->id)->count());
    }

    public function test_a_newer_version_keeps_the_old_signature_intact(): void
    {
        $influencer = F::influencer($this->s);
        $v1 = $this->collaborationTemplate('Deal', ['influencer_profile']);
        $campaign = V::classifiedCampaign($this->s, V::classifiedListing($this->s), [$influencer]);
        $this->service()->sign($this->s->vendor, $this->owner, $campaign, $this->service()->requirementsFor($this->s->vendor, [$influencer->id]), 'Owner', 'en', '127.0.0.1', 'phpunit');
        $old = MarketerSellerContract::sole();
        $oldHash = $old->rendered_content_hash;

        $v2 = $this->collaborationTemplate('Deal', ['influencer_profile'], null, ['version' => 2, 'is_published' => false, 'content_en' => '<p>Changed terms</p>']);
        $this->service()->publish($v2);

        $this->assertFalse($v1->fresh()->is_published);
        $old->refresh();
        $this->assertSame(1, $old->template_version);
        $this->assertSame($oldHash, $old->rendered_content_hash);
        $this->assertSame('active', $old->status);
        $this->assertStringNotContainsString('Changed terms', $old->rendered_content);
        $this->assertSame([$v2->id], $this->service()->requirementsFor($this->s->vendor, [$influencer->id])->keys()->all());

        $this->expectException(\LogicException::class);
        $old->update(['rendered_content' => 'tampered']);
    }

    public function test_an_open_call_contract_covers_affiliates_and_brokers_who_join_later(): void
    {
        $listing = V::classifiedListing($this->s);
        $broker = V::broker($this->s);
        $affiliate = F::affiliate($this->s);
        $brokerDeal = $this->collaborationTemplate('Broker deal', ['broker_profile']);
        $payload = ['classified_listing_id' => $listing->id, 'max_commission_budget' => 800, 'open_to_affiliates' => 1];

        $this->submit($payload)->assertSessionHasErrors('collaboration_contracts');
        $this->submit($payload + ['collab_contracts' => [$brokerDeal->id], 'collab_signer_name' => 'Owner'])->assertSessionHasNoErrors();

        $campaign = $this->campaignsOf($listing)->sole();
        $open = MarketerSellerContract::sole();
        $this->assertNull($open->marketer_id, 'the seller signs for the type, the marketer is not known yet');

        $invitation = app(MarketerCampaignService::class)->dispatchInvitation($campaign, $broker->id);
        app(MarketerCampaignService::class)->dispatchInvitation($campaign, $affiliate->id);

        $covered = MarketerSellerContract::where('marketer_id', $broker->id)->sole();
        $this->assertSame($open->id, $covered->parent_id);
        $this->assertSame($open->rendered_content_hash, $covered->rendered_content_hash);
        $this->assertSame(0, MarketerSellerContract::where('marketer_id', $affiliate->id)->count(), 'the affiliate type has no contract');

        app(MarketerCampaignService::class)->dispatchInvitation($campaign, $broker->id);
        $this->assertSame(1, MarketerSellerContract::where('marketer_id', $broker->id)->count(), 'a second invitation does not duplicate the coverage');
        $this->assertNotNull($invitation);
    }

    public function test_the_sellers_signing_does_not_touch_other_sellers(): void
    {
        $listing = V::classifiedListing($this->s);
        $influencer = F::influencer($this->s);
        $template = $this->collaborationTemplate('Deal', ['influencer_profile']);

        $this->submit(['classified_listing_id' => $listing->id, 'max_commission_budget' => 500, 'marketer_ids' => [$influencer->id], 'collab_contracts' => [$template->id], 'collab_signer_name' => 'Owner'])
            ->assertSessionHasNoErrors();

        $otherSeller = $this->vendorIn($this->s->country);
        $this->assertSame(0, MarketerSellerContract::where('vendor_id', $otherSeller->id)->count());
        $this->assertSame(1, MarketerSellerContract::where('vendor_id', $this->s->vendor->id)->count());
    }
}
