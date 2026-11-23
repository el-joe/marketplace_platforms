<?php

namespace Tests\Feature\Auth;

use App\Models\Marketer;
use App\Models\MarketerAdmin;
use App\Models\Vendor;
use App\Models\VendorAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\Support\CredentialMailFixtures;
use Tests\TestCase;

/**
 * Seller / marketer "forgot password" answers the same for a registered and an unknown email
 * (no account enumeration), in the panel locale, and is rate-limited.
 */
class ForgotPasswordNoEnumerationTest extends TestCase
{
    use CredentialMailFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->useDeliveringMailer();
    }

    private function sellerEmail(): string
    {
        $email = 'seller-'.Str::lower(Str::random(8)).'@example.test';
        VendorAdmin::create([
            'vendor_id' => Vendor::factory()->create()->id, 'name' => 'Seller', 'email' => $email,
            'password' => 'old-password', 'role' => 'owner', 'is_owner' => true, 'is_active' => true,
        ]);

        return $email;
    }

    private function marketerEmail(): string
    {
        $email = 'marketer-'.Str::lower(Str::random(8)).'@example.test';
        MarketerAdmin::create([
            'marketer_id' => Marketer::factory()->create()->id, 'name' => 'M', 'email' => $email,
            'password' => 'old-password', 'is_owner' => true, 'is_active' => true,
        ]);

        return $email;
    }

    public function test_seller_panel_gives_the_same_answer_for_known_and_unknown_emails(): void
    {
        $known = $this->sellerEmail();

        foreach ([$known, 'nobody-'.Str::random(8).'@example.test'] as $email) {
            $this->from(route('partner.auth.forgot'))
                ->post(route('partner.auth.forgot.send'), ['email' => $email])
                ->assertRedirect(route('partner.auth.forgot'))
                ->assertSessionHasNoErrors()
                ->assertSessionHas('status', __('partner.auth.reset_link_sent'));
        }

        $this->assertTrue($this->sentCredentialMail('forgot_password')->hasTo($known));
    }

    public function test_seller_panel_answer_follows_the_panel_locale(): void
    {
        app()->setLocale('en');
        $english = __('partner.auth.reset_link_sent');
        app()->setLocale('ar');

        $this->assertNotSame($english, __('partner.auth.reset_link_sent'));
    }

    public function test_marketer_panel_gives_the_same_answer_for_known_and_unknown_emails(): void
    {
        $known = $this->marketerEmail();

        foreach ([$known, 'nobody-'.Str::random(8).'@example.test'] as $email) {
            $this->from(route('marketer.auth.forgot'))
                ->post(route('marketer.auth.forgot.send'), ['email' => $email])
                ->assertSessionHasNoErrors()
                ->assertSessionHas('status', __('marketer.msg.reset_link_sent'));
        }
    }

    public function test_seller_api_gives_the_same_answer_for_known_and_unknown_emails(): void
    {
        $known = $this->sellerEmail();

        $knownResponse = $this->postJson(route('vendor.auth.forgot-password'), ['email' => $known])->assertOk();
        $unknownResponse = $this->postJson(route('vendor.auth.forgot-password'), ['email' => 'nobody-'.Str::random(8).'@example.test'])->assertOk();

        $this->assertSame($knownResponse->json('message'), $unknownResponse->json('message'));
    }

    public function test_seller_forgot_password_is_rate_limited(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('partner.auth.forgot.send'), ['email' => 'nobody@example.test'])->assertRedirect();
        }

        $this->post(route('partner.auth.forgot.send'), ['email' => 'nobody@example.test'])->assertTooManyRequests();
    }
}
