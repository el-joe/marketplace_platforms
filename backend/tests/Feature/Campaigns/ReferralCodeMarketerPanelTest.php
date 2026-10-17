<?php

namespace Tests\Feature\Campaigns;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\Support\CampaignSampleFixtures as F;
use Tests\Support\MarketplaceScenario;
use Tests\Support\ReferralCodeFixtures as R;
use Tests\TestCase;

/** P14, marketer panel: the marketer who lost an ad sees his code is no longer earning, and until when earlier clicks count. */
class ReferralCodeMarketerPanelTest extends TestCase
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

    public function test_marketer_sees_the_grace_notice_then_the_expired_notice(): void
    {
        $a = F::influencer($this->s);
        $login = F::marketerLogin($a);
        $invA = R::invitationOf(R::pendingCampaign($this->s, $a), $a);
        R::reject($invA->fresh());

        $this->actingAs($login, 'marketer')->get(route('marketer.invitations.index'))
            ->assertOk()
            ->assertSee(__('marketer.invitations.code_inactive_title'))
            ->assertSee(now()->addDays(7)->format('Y-m-d'));

        $this->travel(9)->days();
        $this->actingAs($login, 'marketer')->get(route('marketer.invitations.index'))
            ->assertOk()
            ->assertSee(__('marketer.invitations.code_inactive_expired'));
    }

    public function test_invitation_pages_survive_an_invitation_whose_campaign_was_deleted(): void
    {
        $a = F::influencer($this->s);
        $login = F::marketerLogin($a);
        $campaign = R::pendingCampaign($this->s, $a);
        R::invitationOf($campaign, $a);
        $campaign->delete();

        $this->actingAs($login, 'marketer')->get(route('marketer.invitations.index'))->assertOk();
        $this->actingAs($login, 'marketer')->get(route('marketer.dashboard'))->assertOk();
    }

    public function test_pending_and_accepted_invitations_show_no_inactive_notice(): void
    {
        $a = F::influencer($this->s);
        $login = F::marketerLogin($a);
        R::pendingCampaign($this->s, $a);

        $this->actingAs($login, 'marketer')->get(route('marketer.invitations.index'))
            ->assertOk()
            ->assertDontSee('data-code-inactive', false);
    }

    public function test_notice_strings_exist_in_arabic(): void
    {
        app()->setLocale('ar');
        foreach (['code_inactive_title', 'code_inactive_hint', 'code_inactive_expired'] as $key) {
            $this->assertNotSame('marketer.invitations.'.$key, __('marketer.invitations.'.$key));
        }
    }
}
