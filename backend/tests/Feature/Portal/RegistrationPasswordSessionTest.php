<?php

namespace Tests\Feature\Portal;

use App\Models\Vendor;
use App\Models\VendorAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * The vendor registration wizard keeps step 1 in the session until submit: only a hash of the
 * password is kept there, never the plain text, and neither reaches the rendered page.
 */
class RegistrationPasswordSessionTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Secret-pass-123';

    private MarketplaceScenario $scenario;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->scenario = MarketplaceScenario::make()->build();
    }

    /** @return array<string, string> */
    private function accountStep(string $email, ?string $password = self::PASSWORD): array
    {
        return array_filter([
            'name' => 'Hash Seller',
            'email' => $email,
            'phone' => '+9689'.random_int(1000000, 9999999),
            'gender' => 'female',
            'country_id' => $this->scenario->country->id,
            'password' => $password,
            'password_confirmation' => $password,
        ]);
    }

    public function test_account_step_keeps_only_a_password_hash_in_the_session_and_not_in_the_page(): void
    {
        $email = 'hash-'.Str::lower(Str::random(8)).'@example.test';

        $this->postJson(route('portal.register.step', ['step' => 1]), $this->accountStep($email))->assertOk();

        $saved = session('reg_data.step1');
        $this->assertArrayNotHasKey('password', $saved);
        $this->assertArrayNotHasKey('password_confirmation', $saved);
        $this->assertTrue(Hash::check(self::PASSWORD, $saved['password_hash']));

        $this->get(route('portal.register'))
            ->assertOk()
            ->assertDontSee(self::PASSWORD)
            ->assertDontSee($saved['password_hash'], false)
            ->assertSee($email, false);
    }

    public function test_re_saving_the_account_step_without_retyping_keeps_the_saved_password(): void
    {
        $email = 'resave-'.Str::lower(Str::random(8)).'@example.test';

        $this->postJson(route('portal.register.step', ['step' => 1]), $this->accountStep($email))->assertOk();
        $firstHash = session('reg_data.step1.password_hash');

        $this->postJson(route('portal.register.step', ['step' => 1]), $this->accountStep($email, null))->assertOk();

        $this->assertSame($firstHash, session('reg_data.step1.password_hash'));
    }

    public function test_first_account_step_still_requires_a_password(): void
    {
        $this->postJson(route('portal.register.step', ['step' => 1]), $this->accountStep('nopass@example.test', null))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_completed_registration_signs_in_with_the_password_typed_in_step_one(): void
    {
        $email = 'complete-'.Str::lower(Str::random(8)).'@example.test';
        $account = $this->accountStep($email);

        $this->postJson(route('portal.register.step', ['step' => 1]), $account)->assertOk();
        $this->postJson(route('portal.register.step', ['step' => 2]), [
            'store_name' => 'Hash Store '.Str::random(5),
            'store_slug' => 'hash-store-'.Str::lower(Str::random(6)),
            'business_type' => 'llc',
            'business_name' => 'Hash LLC',
        ])->assertOk();
        $this->postJson(route('portal.register.step', ['step' => 3]), [
            'contact_email' => $email,
            'contact_phone' => $account['phone'],
            'city_id' => $this->scenario->city->id,
            'street_address' => 'Al Khuwair',
        ])->assertOk();

        $this->postJson(route('portal.register.complete'), ['terms_agreed' => '1', 'privacy_agreed' => '1'])->assertOk();

        $this->assertNull(session('reg_data'));
        $this->assertTrue(Hash::check(self::PASSWORD, Vendor::where('email', $email)->value('password')));
        $this->assertTrue(Hash::check(self::PASSWORD, VendorAdmin::where('email', $email)->value('password')));
    }
}
