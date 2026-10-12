<?php

namespace Tests\Feature\Campaigns;

use App\Models\MarketerCampaign;
use App\Notifications\Marketer\PeerPromotionStartedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\Support\CampaignSampleFixtures as F;
use Tests\Support\MarketplaceScenario;
use Tests\Support\PeerPromotionFixtures as P;
use Tests\TestCase;

/** P13 (R11), marketer panel: browse, request, month rule, own-listing block, owner sees the promoter. */
class PeerPromotionMarketerPanelTest extends TestCase
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
    }

    private function requestPeer(string $listingId, $login, string $type = 'marketer_listing')
    {
        return $this->actingAs($login, 'marketer')->post(route('marketer.campaigns.request'), [
            'listing_id' => $listingId,
            'listing_type' => $type,
        ]);
    }

    public function test_browse_page_lists_other_influencers_products_but_not_my_own_or_inactive_ones(): void
    {
        $me = F::influencer($this->s);
        $other = F::influencer($this->s);
        $mine = P::ownListing($this->s, $me);
        $theirs = P::ownListing($this->s, $other);
        $paused = P::ownListing($this->s, $other, 20, 'paused');
        $empty = P::ownListing($this->s, $other, 0);
        $affiliate = P::ownListing($this->s, F::affiliate($this->s));
        $login = F::marketerLogin($me);

        $html = $this->actingAs($login, 'marketer')->get(route('marketer.campaigns.peer-products'))
            ->assertOk()
            ->assertSee($other->name)
            ->getContent();

        $this->assertStringContainsString($theirs->id, $html);
        foreach ([$mine, $paused, $empty, $affiliate] as $hidden) {
            $this->assertStringNotContainsString($hidden->id, $html);
        }
    }

    public function test_request_starts_an_active_promotion_and_shows_it_under_active_campaigns(): void
    {
        $me = F::influencer($this->s);
        $owner = F::influencer($this->s);
        $listing = P::ownListing($this->s, $owner);
        $login = F::marketerLogin($me);

        $this->requestPeer($listing->id, $login)
            ->assertRedirect(route('marketer.campaigns.active'))
            ->assertSessionHas('success', __('marketer.campaigns.peer.started'));

        $campaign = MarketerCampaign::where('marketer_listing_id', $listing->id)->sole();
        $this->assertSame('active', $campaign->status);

        $invitation = $campaign->invitations()->sole();
        $this->actingAs($login, 'marketer')->get(route('marketer.campaigns.active'))
            ->assertOk()
            ->assertSee($invitation->referral_code);
    }

    public function test_second_request_for_the_same_owner_in_the_month_shows_a_clear_error(): void
    {
        $me = F::influencer($this->s);
        $owner = F::influencer($this->s);
        $first = P::ownListing($this->s, $owner);
        $second = P::ownListing($this->s, $owner);
        $login = F::marketerLogin($me);

        $this->requestPeer($first->id, $login)->assertSessionHas('success');
        $this->requestPeer($second->id, $login)
            ->assertSessionHas('error', __('marketer.campaigns.peer.errors.monthly_limit'));

        $this->assertSame(1, MarketerCampaign::whereNotNull('marketer_listing_id')->count());

        // The browse page marks that owner's products as already promoted.
        $this->actingAs($login, 'marketer')->get(route('marketer.campaigns.peer-products'))
            ->assertSee(__('marketer.campaigns.peer.already_this_month'));
    }

    public function test_promoting_my_own_listing_is_blocked_with_a_message(): void
    {
        $me = F::influencer($this->s);
        $mine = P::ownListing($this->s, $me);

        $this->requestPeer($mine->id, F::marketerLogin($me))
            ->assertSessionHas('error', __('marketer.campaigns.peer.errors.self_promotion'));
        $this->assertSame(0, MarketerCampaign::whereNotNull('marketer_listing_id')->count());
    }

    public function test_inactive_and_out_of_stock_listings_are_refused_with_messages(): void
    {
        $me = F::influencer($this->s);
        $owner = F::influencer($this->s);
        $login = F::marketerLogin($me);

        $this->requestPeer(P::ownListing($this->s, $owner, 20, 'paused')->id, $login)
            ->assertSessionHas('error', __('marketer.campaigns.peer.errors.listing_not_active'));
        $this->requestPeer(P::ownListing($this->s, $owner, 0)->id, $login)
            ->assertSessionHas('error', __('marketer.campaigns.peer.errors.insufficient_stock'));
    }

    public function test_messages_exist_in_arabic_too(): void
    {
        app()->setLocale('ar');
        foreach (['self_promotion', 'listing_not_active', 'not_peer_listing', 'not_influencer', 'monthly_limit', 'insufficient_stock'] as $reason) {
            $this->assertNotSame('marketer.campaigns.peer.errors.'.$reason, __('marketer.campaigns.peer.errors.'.$reason));
        }
    }

    public function test_owner_sees_which_influencer_promoted_his_product(): void
    {
        $owner = F::influencer($this->s);
        $promoter = F::influencer($this->s);
        $uninvolved = F::influencer($this->s);
        $listing = P::ownListing($this->s, $owner);
        P::request($promoter, $listing);

        $ownerLogin = F::marketerLogin($owner);
        $this->actingAs($ownerLogin, 'marketer')->get(route('marketer.campaigns.promoted-by-others'))
            ->assertOk()
            ->assertSee($promoter->name)
            ->assertSee(__('marketer.campaigns.peer.promoter'));

        // Not visible to anyone else (neither the promoter nor an unrelated influencer).
        $this->actingAs(F::marketerLogin($uninvolved), 'marketer')->get(route('marketer.campaigns.promoted-by-others'))
            ->assertOk()
            ->assertDontSee($promoter->name)
            ->assertSee(__('marketer.campaigns.peer.promoted_none'));
    }

    public function test_owner_gets_an_in_app_notification_naming_the_promoter(): void
    {
        $owner = F::influencer($this->s);
        $promoter = F::influencer($this->s);
        $ownerLogin = F::marketerLogin($owner);

        P::request($promoter, P::ownListing($this->s, $owner));

        Notification::assertSentTo($ownerLogin, PeerPromotionStartedNotification::class);
    }

    public function test_api_request_and_promoted_by_others(): void
    {
        $owner = F::influencer($this->s);
        $promoter = F::influencer($this->s);
        $listing = P::ownListing($this->s, $owner);
        $promoterLogin = F::marketerLogin($promoter);

        $this->actingAs($promoterLogin, 'marketer_api')
            ->postJson('/api/marketer/campaigns/request', ['listing_id' => $listing->id, 'listing_type' => 'marketer_listing'])
            ->assertCreated();

        $this->actingAs($promoterLogin, 'marketer_api')
            ->postJson('/api/marketer/campaigns/request', ['listing_id' => P::ownListing($this->s, $owner)->id, 'listing_type' => 'marketer_listing'])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'monthly_limit');

        $this->actingAs(F::marketerLogin($owner), 'marketer_api')
            ->getJson('/api/marketer/campaigns/promoted-by-others')
            ->assertOk()
            ->assertJsonPath('data.data.0.promoter.name', $promoter->name);
    }
}
