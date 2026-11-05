<?php

namespace Tests\Feature\Credentials;

use App\Models\Vendor;
use App\Models\VendorAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\Support\CredentialMailFixtures;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * Registration keeps the application when the confirmation email cannot be sent, but the
 * failure is logged with context and the applicant is told on the success page.
 */
class PortalRegistrationEmailFailureTest extends TestCase
{
    use CredentialMailFixtures;
    use RefreshDatabase;

    public function test_a_failed_confirmation_email_is_logged_and_flagged_instead_of_swallowed(): void
    {
        Log::spy();
        $this->makeMailerFail('Connection timed out');
        $scenario = MarketplaceScenario::make()->build();
        $email = 'applicant@gmail.com';

        $this->withSession(['reg_data' => [
            'step1' => ['name' => 'Amina Seller', 'email' => $email, 'phone' => '+96890000000', 'gender' => 'female', 'country_id' => $scenario->country->id, 'password' => 'password123'],
            'step2' => ['store_name' => 'Amina Store', 'store_slug' => 'amina-store', 'business_type' => 'llc', 'business_name' => 'Amina LLC'],
            'step3' => ['contact_email' => $email, 'contact_phone' => '+96890000000', 'city_id' => $scenario->city->id, 'street_address' => 'Al Khuwair'],
            'documents' => [],
        ]])->postJson(route('portal.register.complete'), ['terms_agreed' => '1', 'privacy_agreed' => '1'])
            ->assertOk()->assertJsonPath('success', true);

        $vendor = Vendor::where('email', $email)->firstOrFail();
        $this->assertSame(1, VendorAdmin::where('vendor_id', $vendor->id)->count());
        $this->assertTrue(session('registration_email_failed'));
        $this->assertCredentialFailureLogged('vendor_application_received', $email);
    }

    public function test_success_page_tells_the_applicant_when_the_confirmation_email_failed(): void
    {
        $this->withSession(['registration_email_failed' => true, 'locale' => 'en'])
            ->get(route('portal.register.success'))
            ->assertOk()
            ->assertSee('could not send the confirmation email');

        $this->flushSession();
        $this->withSession(['locale' => 'en'])->get(route('portal.register.success'))
            ->assertDontSee('could not send the confirmation email');
    }
}
