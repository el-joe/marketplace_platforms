<?php

namespace Tests\Feature\Auth;

use App\Enums\CustomerStatus;
use App\Mail\Customer\CustomerPasswordResetMail;
use App\Models\Country;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Storefront "forgot password": request a link by email → open the storefront reset page →
 * set a new password → log in with it. Unknown accounts get the same answer.
 */
class CustomerPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private Country $country;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['app.frontend_url' => 'https://shop.example.test']);

        $this->country = Country::factory()->create(['site_code' => 'ae'.Str::lower(Str::random(6))]);
    }

    private function customer(): Customer
    {
        return Customer::create([
            'name' => 'Layla',
            'email' => 'layla-'.Str::lower(Str::random(8)).'@example.test',
            'password' => 'old-password',
            'country_id' => $this->country->id,
            'status' => CustomerStatus::Active,
        ]);
    }

    private function forgot(string $emailOrPhone, string $locale = 'ar'): TestResponse
    {
        return $this->postJson(route('customer.auth.forgot-password', ['country' => $this->country->site_code]), [
            'email_or_phone' => $emailOrPhone,
            'locale' => $locale,
        ]);
    }

    public function test_customer_resets_password_from_emailed_storefront_link_and_logs_in(): void
    {
        $customer = $this->customer();

        $this->forgot($customer->email)->assertOk()->assertJsonPath('success', true);

        $sent = Mail::sent(CustomerPasswordResetMail::class);
        $this->assertCount(1, $sent);
        $mail = $sent->first();
        $this->assertTrue($mail->hasTo($customer->email));
        $this->assertSame('ar', $mail->locale);
        $this->assertStringStartsWith(
            'https://shop.example.test/'.$this->country->site_code.'-ar/reset-password?token=',
            $mail->resetUrl,
        );

        parse_str((string) parse_url($mail->resetUrl, PHP_URL_QUERY), $query);
        $this->assertSame($customer->email, $query['email']);

        $this->postJson(route('customer.auth.reset-password', ['country' => $this->country->site_code]), [
            'token' => $query['token'],
            'email' => $query['email'],
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertOk();

        $this->assertTrue(Hash::check('brand-new-pass', $customer->fresh()->password));

        $this->postJson(route('customer.auth.login', ['country' => $this->country->site_code]), [
            'email_or_phone' => $customer->email,
            'password' => 'brand-new-pass',
        ])->assertOk()->assertJsonStructure(['data' => ['access_token']]);

        // The link is single-use.
        $this->postJson(route('customer.auth.reset-password', ['country' => $this->country->site_code]), [
            'token' => $query['token'],
            'email' => $query['email'],
            'password' => 'another-pass-1',
            'password_confirmation' => 'another-pass-1',
        ])->assertStatus(422);
    }

    public function test_unknown_email_gets_the_same_response_and_no_mail(): void
    {
        $known = $this->forgot($this->customer()->email)->assertOk()->json();
        Mail::fake();

        $unknown = $this->forgot('nobody-'.Str::random(8).'@example.test')->assertOk()->json();

        $this->assertSame($known, $unknown);
        Mail::assertNothingSent();
    }

    public function test_inactive_customer_gets_no_reset_mail(): void
    {
        $customer = $this->customer();
        $customer->update(['status' => CustomerStatus::Suspended]);

        $this->forgot($customer->email)->assertOk();

        Mail::assertNothingSent();
    }

    public function test_invalid_token_is_rejected_and_password_unchanged(): void
    {
        $customer = $this->customer();

        $this->postJson(route('customer.auth.reset-password', ['country' => $this->country->site_code]), [
            'token' => Str::random(64),
            'email' => $customer->email,
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertStatus(422);

        $this->assertTrue(Hash::check('old-password', $customer->fresh()->password));
    }

    public function test_reset_requires_matching_confirmation(): void
    {
        $this->postJson(route('customer.auth.reset-password', ['country' => $this->country->site_code]), [
            'token' => Str::random(64),
            'email' => 'someone@example.test',
            'password' => 'brand-new-pass',
            'password_confirmation' => 'different-pass',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_reset_email_renders_in_the_storefront_language(): void
    {
        $url = 'https://shop.example.test/uae-ar/reset-password?token=abc&email=a%40b.test';

        $arabic = (new CustomerPasswordResetMail('Layla', 'a@b.test', $url, 60))->locale('ar');
        $arabic->assertSeeInHtml('dir="rtl"', false);
        $arabic->assertSeeInHtml(__('mail.customer_password_reset.action', [], 'ar'));
        $arabic->assertSeeInHtml($url);

        $english = (new CustomerPasswordResetMail('Layla', 'a@b.test', $url, 60))->locale('en');
        $english->assertSeeInHtml('Choose a new password');
        $english->assertDontSeeInHtml(__('mail.customer_password_reset.action', [], 'ar'));
    }
}
