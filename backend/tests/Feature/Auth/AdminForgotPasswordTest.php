<?php

namespace Tests\Feature\Auth;

use App\Enums\AdminStatus;
use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\Support\CredentialMailFixtures;
use Tests\TestCase;

/**
 * Admin login "Forgot password?": request a link → set-password page → sign in with the new password.
 */
class AdminForgotPasswordTest extends TestCase
{
    use CredentialMailFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->useDeliveringMailer();
    }

    public function test_login_page_links_to_forgot_password(): void
    {
        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee(route('admin.password.request'), false);

        $this->get(route('admin.password.request'))->assertOk()->assertSee(route('admin.password.email'), false);
    }

    public function test_admin_resets_password_via_emailed_link_and_signs_in(): void
    {
        $admin = Admin::factory()->create(['email' => 'staff-'.Str::lower(Str::random(6)).'@example.test']);

        $this->from(route('admin.password.request'))
            ->post(route('admin.password.email'), ['email' => $admin->email])
            ->assertRedirect(route('admin.password.request'))
            ->assertSessionHas('status', __('admin.auth.reset_link_sent'));

        $mail = $this->sentCredentialMail('forgot_password');
        $this->assertTrue($mail->hasTo($admin->email));
        $this->assertStringStartsWith(route('admin.password.reset', ['token' => $mail->resetCode]), $mail->setPasswordUrl);

        $this->post(route('admin.password.update'), [
            'token' => $mail->resetCode,
            'email' => $admin->email,
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertRedirect(route('admin.login'));

        $this->assertTrue(Hash::check('brand-new-pass', $admin->fresh()->password));

        $this->post(route('admin.login.submit'), ['email' => $admin->email, 'password' => 'brand-new-pass']);
        $this->assertAuthenticatedAs($admin->fresh(), 'admin');
    }

    public function test_unknown_and_inactive_emails_get_the_same_answer_and_no_mail(): void
    {
        $inactive = Admin::factory()->create(['status' => AdminStatus::Inactive]);

        foreach (['nobody-'.Str::random(8).'@example.test', $inactive->email] as $email) {
            $this->from(route('admin.password.request'))
                ->post(route('admin.password.email'), ['email' => $email])
                ->assertRedirect(route('admin.password.request'))
                ->assertSessionHasNoErrors()
                ->assertSessionHas('status', __('admin.auth.reset_link_sent'));
        }

        Mail::assertNothingSent();
    }

    public function test_forgot_password_requests_are_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('admin.password.email'), ['email' => 'nobody@example.test'])->assertRedirect();
        }

        $this->post(route('admin.password.email'), ['email' => 'nobody@example.test'])->assertStatus(429);
    }
}
