<?php

namespace Tests\Feature\Credentials;

use App\Services\Mail\MailDiagnostics;
use App\Services\SettingsService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CredentialMailFixtures;
use Tests\TestCase;

/**
 * Settings > Integrations: a warning when mail cannot be delivered and a real "Send test email".
 */
class MailSettingsDiagnosticsTest extends TestCase
{
    use CredentialMailFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('en');
        $this->seed(SettingsSeeder::class);
    }

    private function integrationsPage(array $permissions = ['settings.view'])
    {
        return $this->actingAs($this->adminWith($permissions), 'admin')
            ->get(route('admin.settings.index', ['tab' => 'integrations']));
    }

    public function test_banner_warns_when_the_effective_mailer_is_log(): void
    {
        config(['mail.default' => 'log']);

        $this->integrationsPage()->assertOk()
            ->assertSee('id="mail-warning-banner"', false)
            ->assertSee('NOTHING reaches any inbox', false);
    }

    public function test_banner_warns_for_the_array_mailer_and_loopback_smtp_host(): void
    {
        config(['mail.default' => 'array']);
        $this->integrationsPage()->assertSee('id="mail-warning-banner"', false);

        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.from.address' => 'ops@platform.test']);
        $this->integrationsPage()->assertSee('id="mail-warning-banner"', false)->assertSee('(this server itself)', false);
    }

    public function test_no_banner_for_a_real_smtp_configuration(): void
    {
        $this->useDeliveringMailer();

        $this->integrationsPage(['settings.view', 'settings.edit'])->assertOk()->assertDontSee('id="mail-warning-banner"', false)
            ->assertSee('id="btn-send-test-email"', false);
        $this->assertSame([], app(MailDiagnostics::class)->warnings());
    }

    public function test_test_email_button_is_hidden_without_edit_permission(): void
    {
        $this->integrationsPage(['settings.view'])->assertDontSee('id="btn-send-test-email"', false);
    }

    public function test_test_email_requires_the_settings_edit_permission(): void
    {
        $viewer = $this->adminWith(['settings.view']);

        $this->actingAs($viewer, 'admin')->postJson(route('admin.settings.test-email'), ['to' => 'someone@gmail.com'])->assertForbidden();
    }

    public function test_test_email_reports_success_for_any_recipient_domain(): void
    {
        $this->useDeliveringMailer();
        Mail::shouldReceive('raw')->once()->andReturnNull();
        $editor = $this->adminWith(['settings.view', 'settings.edit']);

        $this->actingAs($editor, 'admin')->postJson(route('admin.settings.test-email'), ['to' => 'client@gmail.com'])
            ->assertOk()->assertJson(['success' => true, 'mailer' => 'smtp'])
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'client@gmail.com'));
    }

    public function test_test_email_shows_the_real_smtp_error(): void
    {
        $this->useDeliveringMailer();
        Mail::shouldReceive('raw')->once()->andThrow(new \RuntimeException('535 5.7.8 Authentication credentials invalid'));
        $editor = $this->adminWith(['settings.view', 'settings.edit']);

        $this->actingAs($editor, 'admin')->postJson(route('admin.settings.test-email'))
            ->assertStatus(502)->assertJson(['success' => false])
            ->assertJsonPath('message', fn ($m) => str_contains($m, '535 5.7.8 Authentication credentials invalid'));
    }

    public function test_test_email_does_not_claim_success_when_the_driver_discards_mail(): void
    {
        config(['mail.default' => 'array']);
        $editor = $this->adminWith(['settings.view', 'settings.edit']);

        $this->actingAs($editor, 'admin')->postJson(route('admin.settings.test-email'))
            ->assertStatus(502)->assertJson(['success' => false, 'mailer' => 'array']);
    }

    public function test_test_email_defaults_to_the_admins_own_address_and_validates_input(): void
    {
        config(['mail.default' => 'array']);
        $editor = $this->adminWith(['settings.view', 'settings.edit']);

        $this->actingAs($editor, 'admin')->postJson(route('admin.settings.test-email'), ['to' => 'not-an-email'])
            ->assertStatus(422)->assertJsonValidationErrors('to');

        $this->actingAs($editor, 'admin')->postJson(route('admin.settings.test-email'));
        $messages = app('mailer')->getSymfonyTransport()->messages();
        $this->assertSame($editor->email, $messages->last()->getEnvelope()->getRecipients()[0]->getAddress());
    }

    public function test_mail_host_must_be_a_bare_hostname_without_scheme(): void
    {
        $settings = app(SettingsService::class);

        $this->assertArrayHasKey('mail_host', $settings->validateGroup('integrations', ['mail_host' => 'https://smtp-relay.brevo.com']));
        $this->assertArrayHasKey('mail_host', $settings->validateGroup('integrations', ['mail_host' => 'smtp.example.com/path']));
        $this->assertSame([], $settings->validateGroup('integrations', ['mail_host' => 'smtp.hostinger.com']));
        $this->assertSame([], $settings->validateGroup('integrations', ['mail_host' => '']));
    }
}
