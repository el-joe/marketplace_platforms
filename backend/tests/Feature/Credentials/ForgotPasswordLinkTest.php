<?php

namespace Tests\Feature\Credentials;

use App\Mail\CredentialLinkMail;
use App\Models\Admin;
use App\Models\Country;
use App\Models\Marketer;
use App\Models\MarketerAdmin;
use App\Models\TravelAgency;
use App\Models\Vendor;
use App\Models\VendorAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Tests\Support\CredentialMailFixtures;
use Tests\TestCase;

/**
 * Self-service "forgot password": the application defines no `password.reset` route, so the
 * stock notification could not build a link. Every broker now mails a link to its own panel.
 */
class ForgotPasswordLinkTest extends TestCase
{
    use CredentialMailFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->useDeliveringMailer();
    }

    private function vendorAdmin(): VendorAdmin
    {
        $vendor = Vendor::factory()->create();

        return VendorAdmin::create([
            'vendor_id' => $vendor->id, 'name' => 'Seller', 'email' => 'seller@hotmail.com',
            'password' => 'old-password', 'role' => 'owner', 'is_owner' => true, 'is_active' => true,
        ]);
    }

    public function test_the_application_has_no_default_password_reset_route(): void
    {
        $this->assertFalse(Route::has('password.reset'), 'documents why the stock reset notification cannot be used');
    }

    public function test_seller_forgot_password_emails_a_working_link(): void
    {
        $owner = $this->vendorAdmin();

        $this->post(route('partner.auth.forgot.send'), ['email' => 'seller@hotmail.com'])->assertSessionHasNoErrors();

        $mail = $this->sentCredentialMail('forgot_password');
        $this->assertTrue($mail->hasTo('seller@hotmail.com'));
        $this->assertStringStartsWith(route('partner.auth.reset', ['token' => $mail->resetCode]), $mail->setPasswordUrl);

        $this->post(route('partner.auth.reset.update'), [
            'token' => $mail->resetCode, 'email' => 'seller@hotmail.com',
            'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass',
        ])->assertRedirect(route('partner.login'));

        $this->assertTrue(Hash::check('brand-new-pass', $owner->fresh()->password));
    }

    public function test_api_seller_forgot_password_no_longer_fails_on_a_missing_route(): void
    {
        $this->vendorAdmin();

        $this->postJson(route('vendor.auth.forgot-password'), ['email' => 'seller@hotmail.com'])->assertOk();

        $mail = $this->sentCredentialMail('forgot_password');
        $this->assertStringStartsWith(route('partner.auth.reset', ['token' => $mail->resetCode]), $mail->setPasswordUrl);
    }

    public function test_every_broker_mails_a_link_to_its_own_panel(): void
    {
        $admin = Admin::factory()->create(['email' => 'staff@gmail.com']);
        $marketer = Marketer::factory()->create();
        $marketerAdmin = MarketerAdmin::create([
            'marketer_id' => $marketer->id, 'name' => 'M', 'email' => 'marketer@gmail.com',
            'password' => 'old-password', 'is_owner' => true, 'is_active' => true,
        ]);
        $agency = TravelAgency::create([
            'name' => 'Agency', 'email' => 'agency@gmail.com', 'password' => 'old-password',
            'country_id' => Country::factory()->create()->id, 'status' => 'active',
        ]);
        $this->vendorAdmin();

        $expected = [
            'admins' => [$admin->email, 'admin.password.reset'],
            'vendor_admins' => ['seller@hotmail.com', 'partner.auth.reset'],
            'marketer_admins' => [$marketerAdmin->email, 'marketer.auth.reset'],
        ];

        foreach ($expected as $broker => [$email, $routeName]) {
            $this->assertSame(Password::RESET_LINK_SENT, Password::broker($broker)->sendResetLink(['email' => $email]), $broker);
        }
        $this->assertSame(Password::RESET_LINK_SENT, Password::broker('travel_agencies')->sendResetLink(['email' => $agency->email]));

        $mails = Mail::sent(CredentialLinkMail::class)->keyBy(fn (CredentialLinkMail $mail) => $mail->recipientEmail);
        $this->assertCount(4, $mails);

        foreach ($expected as $broker => [$email, $routeName]) {
            $this->assertStringStartsWith(
                route($routeName, ['token' => $mails[$email]->resetCode]),
                $mails[$email]->setPasswordUrl,
                "{$broker} link must point at {$routeName}",
            );
        }

        // The travel-agency API has no web page: the email carries the reset code instead of a link.
        $this->assertNull($mails['agency@gmail.com']->setPasswordUrl);
        $this->assertStringContainsString($mails['agency@gmail.com']->resetCode, $mails['agency@gmail.com']->render());
    }

    public function test_a_mail_failure_is_logged_and_does_not_reveal_whether_the_address_exists(): void
    {
        Log::spy();
        $this->makeMailerFail();
        $this->vendorAdmin();

        $this->post(route('partner.auth.forgot.send'), ['email' => 'seller@hotmail.com'])->assertSessionHasNoErrors();

        $this->assertCredentialFailureLogged('forgot_password', 'seller@hotmail.com');
    }
}
