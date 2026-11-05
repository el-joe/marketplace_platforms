<?php

namespace Tests\Feature\Credentials;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\Support\CredentialMailFixtures;
use Tests\TestCase;

/**
 * Staff accounts: creation, password reset and "Resend credentials" email a time-limited
 * set-password link (never a password), and a failed send is visible and logged.
 */
class AdminCredentialEmailTest extends TestCase
{
    use CredentialMailFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->useDeliveringMailer();
        Role::firstOrCreate(['name' => 'support', 'guard_name' => 'admin']);
    }

    /**
     * @return array<string, mixed>
     */
    private function newAdminPayload(string $email = 'new.staff@gmail.com'): array
    {
        return [
            'name' => 'New Staff',
            'email' => $email,
            'status' => 'active',
            'roles' => ['support'],
        ];
    }

    public function test_creating_an_admin_emails_a_set_password_link_to_any_domain(): void
    {
        $actor = $this->adminWith(['admins.view', 'admins.create']);

        $response = $this->actingAs($actor, 'admin')->postJson(route('admin.admins.store'), $this->newAdminPayload());

        $response->assertOk()->assertJson(['success' => true, 'email_sent' => true])
            ->assertJsonMissing(['generated_password'])
            ->assertJsonMissingPath('warning');

        $mail = $this->sentCredentialMail('admin_welcome');
        $this->assertTrue($mail->hasTo('new.staff@gmail.com'));
        $this->assertStringContainsString('/reset-password/', $mail->setPasswordUrl);
        $this->assertStringContainsString('email=new.staff%40gmail.com', $mail->setPasswordUrl);
        $this->assertStringContainsString(e($mail->setPasswordUrl), $mail->render());
        $this->assertSame(1440, $mail->expiresInMinutes);
    }

    public function test_admin_is_kept_and_a_warning_shown_when_the_welcome_email_fails(): void
    {
        Log::spy();
        $this->makeMailerFail('Connection refused');
        $actor = $this->adminWith(['admins.view', 'admins.create']);

        $response = $this->actingAs($actor, 'admin')->postJson(route('admin.admins.store'), $this->newAdminPayload());

        $response->assertOk()->assertJson(['success' => true, 'email_sent' => false]);
        $this->assertStringContainsString('new.staff@gmail.com', $response->json('warning'));
        $this->assertDatabaseHas('admins', ['email' => 'new.staff@gmail.com']);
        $this->assertCredentialFailureLogged('admin_created', 'new.staff@gmail.com');
    }

    public function test_warning_is_shown_when_the_mail_driver_does_not_deliver(): void
    {
        config(['mail.default' => 'log']);
        $actor = $this->adminWith(['admins.view', 'admins.create']);

        $response = $this->actingAs($actor, 'admin')->postJson(route('admin.admins.store'), $this->newAdminPayload());

        $response->assertOk()->assertJson(['email_sent' => false]);
        $this->assertStringContainsString('"log"', $response->json('warning'));
    }

    public function test_reset_password_emails_a_link_and_leaves_the_current_password_untouched(): void
    {
        $actor = $this->adminWith(['admins.view', 'admins.edit']);
        $target = Admin::factory()->create(['email' => 'target@outlook.com']);

        $this->actingAs($actor, 'admin')->postJson(route('admin.admins.reset-password', $target->id))
            ->assertOk()->assertJson(['success' => true, 'email_sent' => true]);

        $mail = $this->sentCredentialMail('admin_reset');
        $this->assertTrue($mail->hasTo('target@outlook.com'));
        $this->assertTrue(Hash::check('password', $target->fresh()->password), 'password must not change until the link is used');
    }

    public function test_reset_password_reports_failure_instead_of_claiming_success(): void
    {
        Log::spy();
        $this->makeMailerFail();
        $actor = $this->adminWith(['admins.view', 'admins.edit']);
        $target = Admin::factory()->create(['email' => 'target@outlook.com']);

        $response = $this->actingAs($actor, 'admin')->postJson(route('admin.admins.reset-password', $target->id));

        $response->assertStatus(502)->assertJson(['success' => false, 'email_sent' => false]);
        $this->assertTrue(Hash::check('password', $target->fresh()->password));
        $this->assertCredentialFailureLogged('admin_password_reset', 'target@outlook.com');
    }

    public function test_resend_credentials_requires_the_edit_permission(): void
    {
        $viewer = $this->adminWith(['admins.view']);
        $target = Admin::factory()->create();

        $this->actingAs($viewer, 'admin')->postJson(route('admin.admins.resend-credentials', $target->id))->assertForbidden();

        Mail::assertNothingSent();
    }

    public function test_resend_credentials_sends_a_fresh_welcome_link(): void
    {
        $actor = $this->adminWith(['admins.view', 'admins.edit']);
        $target = Admin::factory()->create(['email' => 'late.joiner@company.io']);

        $this->actingAs($actor, 'admin')->postJson(route('admin.admins.resend-credentials', $target->id))
            ->assertOk()->assertJson(['success' => true, 'email_sent' => true]);

        $mail = $this->sentCredentialMail('admin_welcome');
        $this->assertTrue($mail->hasTo('late.joiner@company.io'));
    }

    public function test_resend_credentials_failure_is_visible(): void
    {
        Log::spy();
        $this->makeMailerFail('535 Authentication failed');
        $actor = $this->adminWith(['admins.view', 'admins.edit']);
        $target = Admin::factory()->create(['email' => 'late.joiner@company.io']);

        $this->actingAs($actor, 'admin')->postJson(route('admin.admins.resend-credentials', $target->id))
            ->assertStatus(502)->assertJson(['success' => false]);

        $this->assertCredentialFailureLogged('admin_credentials_resent', 'late.joiner@company.io');
    }

    public function test_emailed_link_lets_the_admin_choose_a_password_once(): void
    {
        $actor = $this->adminWith(['admins.view', 'admins.create']);
        $this->actingAs($actor, 'admin')->postJson(route('admin.admins.store'), $this->newAdminPayload())->assertOk();
        $mail = $this->sentCredentialMail('admin_welcome');
        $this->app['auth']->forgetGuards();

        $this->get($mail->setPasswordUrl)->assertOk()->assertSee('new.staff@gmail.com');

        $payload = [
            'token' => $mail->resetCode,
            'email' => 'new.staff@gmail.com',
            'password' => 'Chosen-Pass-123',
            'password_confirmation' => 'Chosen-Pass-123',
        ];
        $this->post(route('admin.password.update'), $payload)->assertRedirect(route('admin.login'));

        $admin = Admin::where('email', 'new.staff@gmail.com')->firstOrFail();
        $this->assertTrue(Hash::check('Chosen-Pass-123', $admin->password));

        $this->post(route('admin.password.update'), $payload)->assertSessionHasErrors('email');
    }
}
