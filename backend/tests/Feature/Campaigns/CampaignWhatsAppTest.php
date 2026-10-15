<?php

namespace Tests\Feature\Campaigns;

use App\Contracts\WhatsAppGateway;
use App\Jobs\SendCampaignWhatsAppNotificationJob;
use App\Jobs\SendVendorInvitationResponseWhatsAppJob;
use App\Models\Admin;
use App\Models\MarketerCampaignInvitation;
use App\Models\VendorAdmin;
use App\Notifications\Vendor\CampaignInvitationResponseNotification;
use App\Services\Marketer\InvitationWhatsAppMessage;
use App\Services\MarketerCampaignService;
use App\Services\WhatsApp\LogWhatsAppGateway;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\CampaignSampleFixtures as F;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

class FakeWhatsAppGateway implements WhatsAppGateway
{
    /** @var array<int, array{to: string, message: string}> */
    public array $sent = [];

    public bool $configured = true;

    public bool $succeed = true;

    public ?string $error = null;

    public function send(string $to, string $message): bool
    {
        if (! $this->succeed) {
            $this->error = 'http_503: provider down';

            return false;
        }
        $this->error = null;
        $this->sent[] = ['to' => $to, 'message' => $message];

        return true;
    }

    public function lastError(): ?string
    {
        return $this->error;
    }

    public function isConfigured(): bool
    {
        return $this->configured;
    }
}

/** P10: WhatsApp invitation delivery, signed accept/reject links, admin visibility of failures. */
class CampaignWhatsAppTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $s;

    private FakeWhatsAppGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Notification::fake();
        $this->withoutVite();
        $this->s = MarketplaceScenario::make()->build();
        $this->gateway = new FakeWhatsAppGateway;
        $this->app->instance(WhatsAppGateway::class, $this->gateway);
    }

    /** Active campaign with one invited influencer (+ a spare influencer for replacement). */
    private function invited(bool $spare = false): MarketerCampaignInvitation
    {
        $m = F::influencer($this->s);
        $m->update(['whatsapp_for_campaigns' => '+971500000001']);
        if ($spare) {
            F::influencer($this->s);
        }
        $campaign = F::campaign($this->s, [$m]);
        app(MarketerCampaignService::class)->approveCampaign($campaign, Admin::factory()->create());

        return $campaign->invitations()->where('marketer_id', $m->id)->sole();
    }

    private function runJob(string $id): void
    {
        app()->call([new SendCampaignWhatsAppNotificationJob($id), 'handle']);
    }

    public function test_dispatching_an_invitation_queues_the_whatsapp_job(): void
    {
        $inv = $this->invited();

        Queue::assertPushed(SendCampaignWhatsAppNotificationJob::class, fn ($j) => $j->invitationId === $inv->id);
    }

    public function test_job_sends_once_with_valid_signed_accept_and_reject_links(): void
    {
        $inv = $this->invited();

        $this->runJob($inv->id);
        $this->runJob($inv->id); // second run must not resend

        $this->assertCount(1, $this->gateway->sent);
        $this->assertSame('+971500000001', $this->gateway->sent[0]['to']);
        $text = $this->gateway->sent[0]['message'];
        $links = app(InvitationWhatsAppMessage::class)->links($inv);

        $this->assertStringContainsString($inv->campaign->getPromotedTitle(), $text);
        $this->assertStringContainsString((string) $inv->acceptance_window_hours, $text);
        $this->assertMatchesRegularExpression('#/invitations/'.$inv->id.'/whatsapp/accept\?[^\s]*signature=#', $text);
        $this->assertMatchesRegularExpression('#/invitations/'.$inv->id.'/whatsapp/reject\?[^\s]*signature=#', $text);
        $this->assertTrue(URL::hasValidSignature(Request::create($links['accept'])));

        $fresh = $inv->fresh();
        $this->assertTrue($fresh->whatsapp_sent);
        $this->assertNotNull($fresh->whatsapp_sent_at);
        $this->assertNull($fresh->whatsapp_failed_at);
    }

    public function test_message_is_localised_in_arabic_and_english(): void
    {
        $inv = $this->invited();
        $builder = app(InvitationWhatsAppMessage::class);

        $en = $builder->build($inv, 'en');
        $ar = $builder->build($inv, 'ar');

        $this->assertStringContainsString('Accept:', $en);
        $this->assertStringContainsString('قبول:', $ar);
        $this->assertNotSame($en, $ar);
    }

    public function test_provider_failure_is_recorded_and_not_marked_sent_then_recovers(): void
    {
        $inv = $this->invited();
        $this->gateway->succeed = false;

        $this->runJob($inv->id);

        $fresh = $inv->fresh();
        $this->assertFalse($fresh->whatsapp_sent);
        $this->assertSame('http_503: provider down', $fresh->whatsapp_last_error);
        $this->assertNotNull($fresh->whatsapp_failed_at);
        $this->assertSame(1, $fresh->whatsapp_attempts);
        $this->assertTrue($fresh->whatsappFailed());

        $this->gateway->succeed = true;
        $this->runJob($inv->id);

        $fresh = $inv->fresh();
        $this->assertTrue($fresh->whatsapp_sent);
        $this->assertNull($fresh->whatsapp_last_error);
        $this->assertFalse($fresh->whatsappFailed());
        $this->assertSame(2, $fresh->whatsapp_attempts);
    }

    public function test_unconfigured_provider_and_missing_number_are_recorded_as_failures_without_sending(): void
    {
        $inv = $this->invited();
        $this->gateway->configured = false;
        $this->runJob($inv->id);
        $this->assertCount(0, $this->gateway->sent);
        $this->assertTrue($inv->fresh()->whatsappFailed());

        $inv->marketer->update(['whatsapp_for_campaigns' => null]);
        $this->runJob($inv->id);
        $this->assertSame('missing_whatsapp_number', $inv->fresh()->whatsapp_last_error);
        $this->assertFalse($inv->fresh()->whatsapp_sent);
    }

    public function test_job_failed_hook_stores_the_error_and_backoff_is_configured(): void
    {
        $inv = $this->invited();
        $job = new SendCampaignWhatsAppNotificationJob($inv->id);

        $job->failed(new \RuntimeException('exhausted'));

        $this->assertSame('exhausted', $inv->fresh()->whatsapp_last_error);
        $this->assertGreaterThan(1, count($job->backoff()));
        $this->assertGreaterThan(1, $job->tries);
    }

    public function test_default_driver_is_log_and_never_claims_delivery(): void
    {
        $this->app->forgetInstance(WhatsAppGateway::class);
        $this->app->bind(WhatsAppGateway::class, WhatsAppService::class);

        $gateway = app(WhatsAppGateway::class);
        $this->assertInstanceOf(LogWhatsAppGateway::class, $gateway->driver());
        $this->assertFalse($gateway->isConfigured());
        $this->assertFalse($gateway->send('+971500000001', 'hello'));
        $this->assertSame('whatsapp_not_configured', $gateway->lastError());
    }

    public function test_marketer_panel_accept_link_confirms_then_accepts_and_notifies_the_vendor(): void
    {
        $vendorAdmin = VendorAdmin::create([
            'vendor_id' => $this->s->vendor->id, 'name' => 'Owner', 'email' => 'va-'.Str::lower(Str::random(8)).'@example.test',
            'password' => bcrypt('password'), 'role' => 'owner', 'is_owner' => true, 'is_active' => true,
        ]);
        $inv = $this->invited();
        $links = app(InvitationWhatsAppMessage::class)->links($inv);

        // GET only asks for confirmation (link previews must not accept anything), no login needed.
        $this->get($links['accept'])->assertOk()->assertSee('value="accept"', false);
        $this->assertSame('pending', $inv->fresh()->status);

        $this->post($links['accept'])->assertOk()->assertSee(__('marketer.invitations.whatsapp_accepted'));

        $this->assertSame('accepted', $inv->fresh()->status);
        $this->assertTrue($inv->campaign->samples()->where('invitation_id', $inv->id)->exists());
        Notification::assertSentTo($vendorAdmin, CampaignInvitationResponseNotification::class);
        Queue::assertPushed(SendVendorInvitationResponseWhatsAppJob::class, fn ($j) => $j->outcome === 'accepted');
    }

    public function test_reject_link_rejects_and_dispatches_a_replacement(): void
    {
        $inv = $this->invited(spare: true);
        $links = app(InvitationWhatsAppMessage::class)->links($inv);

        $this->get($links['reject'])->assertOk()->assertSee('name="reason"', false);
        $this->post($links['reject'], ['reason' => 'busy'])->assertOk()->assertSee(__('marketer.invitations.whatsapp_rejected'));

        $this->assertSame('rejected', $inv->fresh()->status);
        $this->assertSame('busy', $inv->fresh()->decline_reason);
        $replacement = $inv->campaign->invitations()->where('replaced_invitation_id', $inv->id)->first()
            ?? $inv->campaign->invitations()->where('id', '!=', $inv->id)->first();
        $this->assertNotNull($replacement);
        $this->assertSame('pending', $replacement->status);
        Queue::assertPushed(SendCampaignWhatsAppNotificationJob::class, fn ($j) => $j->invitationId === $replacement->id);
    }

    public function test_used_link_shows_friendly_unavailable_page(): void
    {
        $inv = $this->invited();
        $links = app(InvitationWhatsAppMessage::class)->links($inv);

        $this->post($links['accept'])->assertOk();
        $this->post($links['reject'])->assertOk()->assertSee(__('marketer.invitations.whatsapp_unavailable'));
        $this->get($links['accept'])->assertOk()->assertSee(__('marketer.invitations.whatsapp_unavailable'));
        $this->assertSame('accepted', $inv->fresh()->status);
    }

    public function test_tampered_or_expired_signature_gets_403_friendly_page_and_changes_nothing(): void
    {
        $inv = $this->invited();
        $links = app(InvitationWhatsAppMessage::class)->links($inv);

        $this->post($links['accept'].'x')->assertForbidden()->assertSee(__('marketer.invitations.whatsapp_link_expired'));
        $this->get(route('marketer.invitations.whatsapp.accept', $inv))->assertForbidden();

        $expired = URL::temporarySignedRoute('marketer.invitations.whatsapp.accept', now()->subMinute(), ['invitation' => $inv->id]);
        $this->post($expired)->assertForbidden()->assertSee(__('marketer.invitations.whatsapp_link_expired'));
        $this->assertSame('pending', $inv->fresh()->status);
    }

    public function test_admin_campaign_page_shows_failed_whatsapp_sends(): void
    {
        $perms = ['vendors.assigned_only', 'marketer_campaigns.view'];
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'admin']);
        }
        $admin = Admin::factory()->create();
        $admin->givePermissionTo(array_slice($perms, 1));

        $inv = $this->invited();
        $this->gateway->succeed = false;
        $this->runJob($inv->id);

        foreach (['en', 'ar'] as $locale) {
            app()->setLocale($locale);
            $this->actingAs($admin, 'admin')
                ->get(route('admin.marketer-campaigns.show', $inv->campaign_id))
                ->assertOk()
                ->assertSee('data-testid="whatsapp-failures"', false)
                ->assertSee('http_503: provider down')
                ->assertSee(__('admin.marketer_campaigns.whatsapp_failed'));
        }

        $this->gateway->succeed = true;
        $this->runJob($inv->id);
        $this->actingAs($admin, 'admin')
            ->get(route('admin.marketer-campaigns.show', $inv->campaign_id))
            ->assertOk()
            ->assertDontSee('data-testid="whatsapp-failures"', false);
    }

    public function test_vendor_whatsapp_is_optional_and_behind_a_setting(): void
    {
        $this->s->vendor->update(['whatsapp_number' => '+971500000009']);
        $inv = $this->invited();

        (new SendVendorInvitationResponseWhatsAppJob($inv->id, 'accepted'))->handle($this->gateway);
        $this->assertCount(0, $this->gateway->sent);

        F::setting('marketer_whatsapp_notify_vendor', '1');
        (new SendVendorInvitationResponseWhatsAppJob($inv->id, 'rejected'))->handle($this->gateway);

        $this->assertCount(1, $this->gateway->sent);
        $this->assertSame('+971500000009', $this->gateway->sent[0]['to']);
        $this->assertStringContainsString($inv->marketer->name, $this->gateway->sent[0]['message']);
    }
    // ───────── admin "Resend" (P10 follow-up) ─────────

    private function resendAdmin(bool $canApprove = true): Admin
    {
        $perms = ['vendors.assigned_only', 'marketer_campaigns.view', 'marketer_campaigns.approve'];
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'admin']);
        }
        $admin = Admin::factory()->create();
        $admin->givePermissionTo($canApprove ? ['marketer_campaigns.view', 'marketer_campaigns.approve'] : ['marketer_campaigns.view']);

        return $admin;
    }

    private function resendUrl(MarketerCampaignInvitation $inv): string
    {
        return route('admin.marketer-campaigns.invitations.resend-whatsapp', [$inv->campaign_id, $inv->id]);
    }

    public function test_admin_can_resend_a_pending_invitation_and_the_delivery_is_forced(): void
    {
        $inv = $this->invited();
        $this->runJob($inv->id);
        $this->assertCount(1, $this->gateway->sent);
        $admin = $this->resendAdmin();

        foreach (['en', 'ar'] as $locale) {
            $this->withSession(['locale' => $locale])->actingAs($admin, 'admin')
                ->get(route('admin.marketer-campaigns.show', $inv->campaign_id))
                ->assertOk()->assertSee('data-resend-whatsapp', false)->assertSee(__('admin.marketer_campaigns.whatsapp_resend'));
        }

        Queue::fake(); // forget the dispatch-time push
        $this->actingAs($admin, 'admin')->post($this->resendUrl($inv))->assertRedirect()
            ->assertSessionHas('success', __('admin.marketer_campaigns.whatsapp_resend_queued'));
        Queue::assertPushed(SendCampaignWhatsAppNotificationJob::class, fn ($j) => $j->invitationId === $inv->id && $j->force === true);

        // The forced job delivers again although the first send succeeded; the normal job still does not.
        app()->call([new SendCampaignWhatsAppNotificationJob($inv->id, true), 'handle']);
        $this->assertCount(2, $this->gateway->sent);
        $this->runJob($inv->id);
        $this->assertCount(2, $this->gateway->sent);
        $this->assertSame(1, $inv->fresh()->whatsapp_resend_count);
    }

    public function test_resend_is_rate_limited_by_a_cooldown(): void
    {
        $inv = $this->invited();
        $admin = $this->resendAdmin();
        F::setting('marketer_whatsapp_resend_cooldown_minutes', 10);
        Queue::fake();

        $this->actingAs($admin, 'admin')->post($this->resendUrl($inv))->assertSessionHas('success');
        $this->actingAs($admin, 'admin')->post($this->resendUrl($inv))->assertSessionHas('error');
        $this->actingAs($admin, 'admin')->post($this->resendUrl($inv))->assertSessionHas('error');
        Queue::assertPushed(SendCampaignWhatsAppNotificationJob::class, 1);
        $this->assertSame(1, $inv->fresh()->whatsapp_resend_count);

        $this->travel(11)->minutes();
        $this->actingAs($admin, 'admin')->post($this->resendUrl($inv))->assertSessionHas('success');
        Queue::assertPushed(SendCampaignWhatsAppNotificationJob::class, 2);
        $this->assertSame(2, $inv->fresh()->whatsapp_resend_count);
    }

    public function test_only_pending_unexpired_invitations_can_be_resent(): void
    {
        $inv = $this->invited();
        $admin = $this->resendAdmin();
        Queue::fake();

        foreach (['accepted', 'rejected', 'timed_out', 'cancelled'] as $status) {
            $inv->update(['status' => $status]);
            $this->actingAs($admin, 'admin')->post($this->resendUrl($inv))->assertSessionHas('error', __('admin.marketer_campaigns.whatsapp_resend_not_pending'));
            $this->actingAs($admin, 'admin')->get(route('admin.marketer-campaigns.show', $inv->campaign_id))->assertDontSee('data-resend-whatsapp', false);
        }

        $inv->update(['status' => 'pending', 'expires_at' => now()->subMinute()]);
        $this->actingAs($admin, 'admin')->post($this->resendUrl($inv))->assertSessionHas('error');

        Queue::assertNothingPushed();
        $this->assertNull($inv->fresh()->whatsapp_resent_at);
    }

    public function test_resend_requires_the_approve_permission_and_the_right_campaign(): void
    {
        $inv = $this->invited();
        $other = $this->invited();
        Queue::fake();

        $viewer = $this->resendAdmin(canApprove: false);
        $this->actingAs($viewer, 'admin')->post($this->resendUrl($inv))->assertForbidden();
        $this->actingAs($viewer, 'admin')->get(route('admin.marketer-campaigns.show', $inv->campaign_id))
            ->assertOk()->assertDontSee('data-resend-whatsapp', false);

        $this->actingAs($this->resendAdmin(), 'admin')
            ->post(route('admin.marketer-campaigns.invitations.resend-whatsapp', [$other->campaign_id, $inv->id]))
            ->assertNotFound();

        Queue::assertNothingPushed();
    }
}
