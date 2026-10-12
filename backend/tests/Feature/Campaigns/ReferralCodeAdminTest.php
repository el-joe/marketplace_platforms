<?php

namespace Tests\Feature\Campaigns;

use App\Models\Admin;
use App\Models\Setting;
use App\Services\MarketerCampaignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\Support\CampaignSampleFixtures as F;
use Tests\Support\MarketplaceScenario;
use Tests\Support\ReferralCodeFixtures as R;
use Tests\TestCase;

/** P14, admin panel: code-history chain on the campaign page and the grace-days setting. */
class ReferralCodeAdminTest extends TestCase
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

        foreach (['vendors.assigned_only', 'marketer_campaigns.view', 'marketer_commission_settings.view', 'marketer_fee_settings.edit'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'admin']);
        }
        $this->admin = Admin::factory()->create();
        $this->admin->givePermissionTo(['marketer_campaigns.view', 'marketer_commission_settings.view', 'marketer_fee_settings.edit']);
    }

    public function test_campaign_page_shows_the_code_chain_with_states(): void
    {
        $a = F::influencer($this->s);
        F::influencer($this->s); // the replacement
        $campaign = R::pendingCampaign($this->s, $a);
        $invA = R::invitationOf($campaign, $a);
        R::reject($invA->fresh());
        $invB = $campaign->invitations()->where('replaced_invitation_id', $invA->id)->firstOrFail();
        app(MarketerCampaignService::class)->acceptInvitation($invB);

        $html = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.marketer-campaigns.show', $campaign))
            ->assertOk()
            ->assertSee(__('admin.marketer_campaigns.code_history_title'))
            ->assertSee($invA->referral_code)
            ->assertSee($invB->referral_code)
            ->assertSee(__('admin.marketer_campaigns.code_replaces'))
            ->getContent();

        $this->assertStringContainsString('data-code-row="'.$invA->referral_code.'" data-code-state="grace"', $html);
        $this->assertStringContainsString('data-code-row="'.$invB->referral_code.'" data-code-state="active"', $html);
        // oldest first: the replaced code is listed before its successor
        $this->assertLessThan(strpos($html, 'data-code-row="'.$invB->referral_code), strpos($html, 'data-code-row="'.$invA->referral_code));
    }

    public function test_code_flips_to_expired_once_the_grace_window_has_passed(): void
    {
        $a = F::influencer($this->s);
        $campaign = R::pendingCampaign($this->s, $a);
        $invA = R::invitationOf($campaign, $a);
        R::reject($invA->fresh());

        $this->travel(8)->days();

        $html = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.marketer-campaigns.show', $campaign))->assertOk()->getContent();
        $this->assertStringContainsString('data-code-row="'.$invA->referral_code.'" data-code-state="expired"', $html);
    }

    public function test_chain_page_renders_in_arabic(): void
    {
        app()->setLocale('ar');
        foreach (['code_history_title', 'code_state_grace', 'code_state_expired', 'code_state_active', 'code_replaces', 'code_col_code'] as $key) {
            $this->assertNotSame('admin.marketer_campaigns.'.$key, __('admin.marketer_campaigns.'.$key));
        }
        foreach (['referral_grace_title', 'referral_grace_hint', 'referral_grace_days', 'referral_grace_saved'] as $key) {
            $this->assertNotSame('admin.marketer_settings.'.$key, __('admin.marketer_settings.'.$key));
        }
    }

    public function test_settings_page_shows_and_saves_the_grace_days(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.marketer-settings.index'))
            ->assertOk()
            ->assertSee(__('admin.marketer_settings.referral_grace_title'))
            ->assertSee('name="marketer_referral_grace_days"', false)
            ->assertSee('value="7"', false);

        $this->post(route('admin.marketer-settings.update-referral-grace'), ['marketer_referral_grace_days' => 3])
            ->assertRedirect()->assertSessionHas('success');

        Setting::flush();
        $this->assertSame(3, (int) setting('marketer_referral_grace_days'));
    }

    public function test_grace_days_are_validated_and_permission_protected(): void
    {
        $this->actingAs($this->admin, 'admin');
        foreach ([-1, 91, 'abc', ''] as $bad) {
            $this->post(route('admin.marketer-settings.update-referral-grace'), ['marketer_referral_grace_days' => $bad])
                ->assertSessionHasErrors('marketer_referral_grace_days');
        }

        $viewer = Admin::factory()->create();
        $viewer->givePermissionTo('marketer_commission_settings.view');
        $this->actingAs($viewer, 'admin')
            ->post(route('admin.marketer-settings.update-referral-grace'), ['marketer_referral_grace_days' => 2])
            ->assertForbidden();
    }

    public function test_the_setting_is_seeded_with_the_default_of_seven(): void
    {
        $this->assertNotNull(Setting::where('key', 'marketer_referral_grace_days')->first());
        $this->assertSame(7, (int) setting('marketer_referral_grace_days'));
    }
}
