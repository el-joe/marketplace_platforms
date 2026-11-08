<?php

namespace Tests\Feature\Portal;

use App\Enums\VendorType;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * The Open Souq landing reuses the /vendors/register wizard but must create a classified-ads vendor, so the new
 * account can open the partner classifieds section; every other entry point keeps creating a product vendor.
 */
class OpenSouqVendorRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $scenario;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->scenario = MarketplaceScenario::make()->build();
    }

    /**
     * @return array<string, mixed>
     */
    private function registrationData(?string $vendorType): array
    {
        $email = 'souq-seller@example.test';

        return array_filter([
            'step1' => ['name' => 'Amina Seller', 'email' => $email, 'phone' => '+96890000000', 'gender' => 'female', 'country_id' => $this->scenario->country->id, 'password' => 'password123'],
            'step2' => ['store_name' => 'Amina Souq', 'store_slug' => 'amina-souq', 'business_type' => 'llc', 'business_name' => 'Amina LLC'],
            'step3' => ['contact_email' => $email, 'contact_phone' => '+96890000000', 'city_id' => $this->scenario->city->id, 'street_address' => 'Al Khuwair'],
            'documents' => [],
            'vendor_type' => $vendorType,
        ], fn ($value) => $value !== null);
    }

    public function test_the_open_souq_landing_sends_applicants_to_the_classified_registration(): void
    {
        $this->get(route('portal.companies.open-souq'))
            ->assertOk()
            ->assertSee(route('portal.register', ['account' => 'classified']), false);
    }

    public function test_registering_from_open_souq_creates_a_classified_vendor(): void
    {
        $this->get(route('portal.register', ['account' => 'classified']))
            ->assertOk()
            ->assertSee('data-testid="classified-account-notice"', false)
            ->assertSessionHas('reg_data.vendor_type', VendorType::ClassifiedVendor->value);

        $this->withSession(['reg_data' => $this->registrationData(VendorType::ClassifiedVendor->value)])
            ->postJson(route('portal.register.complete'), ['terms_agreed' => '1', 'privacy_agreed' => '1'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $vendor = Vendor::where('email', 'souq-seller@example.test')->firstOrFail();
        $this->assertTrue($vendor->isClassifiedVendor());
    }

    public function test_the_regular_registration_still_creates_a_product_vendor(): void
    {
        $this->get(route('portal.register'))
            ->assertOk()
            ->assertDontSee('data-testid="classified-account-notice"', false)
            ->assertSessionHas('reg_data.vendor_type', VendorType::ProductVendor->value);

        $this->withSession(['reg_data' => $this->registrationData(null)])
            ->postJson(route('portal.register.complete'), ['terms_agreed' => '1', 'privacy_agreed' => '1'])
            ->assertOk();

        $this->assertTrue(Vendor::where('email', 'souq-seller@example.test')->firstOrFail()->isProductVendor());
    }
}
