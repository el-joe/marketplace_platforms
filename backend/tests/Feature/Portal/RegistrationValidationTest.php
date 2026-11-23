<?php

namespace Tests\Feature\Portal;

use App\Models\Vendor;
use App\Models\VendorDocumentCountryRequirement;
use App\Models\VendorDocumentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * Server-side validation of the vendor registration wizard: duplicate store names and documents the vendor's
 * country marks as mandatory, independently of the client-side wizard.
 */
class RegistrationValidationTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'docs-seller@example.test';

    private MarketplaceScenario $scenario;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->scenario = MarketplaceScenario::make()->build();
    }

    private function requireDocument(string $level): void
    {
        $type = VendorDocumentType::create([
            'code' => 'business_license',
            'name_en' => 'Business License',
            'name_ar' => 'السجل التجاري',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        VendorDocumentCountryRequirement::create([
            'vendor_document_type_id' => $type->id,
            'country_id' => $this->scenario->country->id,
            'requirement_level' => $level,
        ]);
    }

    /**
     * @param  array<string, string>  $documents
     * @return array<string, mixed>
     */
    private function registrationSession(array $documents): array
    {
        return ['reg_data' => [
            'step1' => ['name' => 'Docs Seller', 'email' => self::EMAIL, 'phone' => '+96890000001', 'gender' => 'male', 'country_id' => $this->scenario->country->id, 'password' => 'password123'],
            'step2' => ['store_name' => 'Docs Store', 'store_slug' => 'docs-store', 'business_type' => 'llc', 'business_name' => 'Docs LLC'],
            'step3' => ['contact_email' => self::EMAIL, 'contact_phone' => '+96890000001', 'city_id' => $this->scenario->city->id, 'street_address' => 'Al Khuwair'],
            'documents' => $documents,
        ]];
    }

    public function test_registration_is_refused_when_a_mandatory_document_is_missing(): void
    {
        $this->requireDocument('mandatory');

        $this->withSession($this->registrationSession([]))
            ->postJson(route('portal.register.complete'), ['terms_agreed' => '1', 'privacy_agreed' => '1'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('documents');

        $this->assertDatabaseMissing('vendors', ['email' => self::EMAIL]);
    }

    public function test_store_details_step_rejects_a_store_name_already_taken(): void
    {
        Vendor::factory()->create(['store_name' => 'Docs Store']);

        $this->postJson(route('portal.register.step', ['step' => 2]), [
            'store_name' => 'Docs Store',
            'store_slug' => 'another-slug',
            'business_type' => 'llc',
            'business_name' => 'Docs LLC',
        ])->assertUnprocessable()->assertJsonValidationErrors('store_name');
    }

    public function test_registration_succeeds_when_the_missing_document_is_optional(): void
    {
        $this->requireDocument('optional');

        $this->withSession($this->registrationSession([]))
            ->postJson(route('portal.register.complete'), ['terms_agreed' => '1', 'privacy_agreed' => '1'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertTrue(Vendor::where('email', self::EMAIL)->exists());
    }

    public function test_account_step_rejects_a_phone_already_registered_with_a_new_email(): void
    {
        Vendor::factory()->create(['phone' => '+96890000001']);

        $this->postJson(route('portal.register.step', ['step' => 1]), [
            'name' => 'Docs Seller',
            'email' => self::EMAIL,
            'phone' => '+96890000001',
            'gender' => 'male',
            'country_id' => $this->scenario->country->id,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['phone' => __('portal.registration.phone_unique')]);
    }

    public function test_submit_rejects_a_phone_taken_after_the_account_step_without_leaking_internals(): void
    {
        Vendor::factory()->create(['phone' => '+96890000001']);

        $response = $this->withSession($this->registrationSession([]))
            ->postJson(route('portal.register.complete'), ['terms_agreed' => '1', 'privacy_agreed' => '1'])
            ->assertUnprocessable()
            ->assertJsonPath('step', 1)
            ->assertJsonValidationErrors(['phone' => __('portal.registration.phone_unique')]);

        $this->assertResponseHidesInternals($response->getContent());
        $this->assertDatabaseMissing('vendors', ['email' => self::EMAIL]);
    }

    public function test_a_unique_constraint_race_on_submit_becomes_a_localized_validation_error(): void
    {
        // Another registration takes the phone between the pre-check and the INSERT.
        $raced = false;
        Vendor::creating(function (Vendor $vendor) use (&$raced): void {
            if ($vendor->email === self::EMAIL && ! $raced) {
                $raced = true;
                Vendor::factory()->create(['phone' => $vendor->phone]);
            }
        });

        $response = $this->withSession($this->registrationSession([]))
            ->postJson(route('portal.register.complete'), ['terms_agreed' => '1', 'privacy_agreed' => '1'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone' => __('portal.registration.phone_unique')]);

        $this->assertTrue($raced);
        $this->assertResponseHidesInternals($response->getContent());
        $this->assertDatabaseMissing('vendors', ['email' => self::EMAIL]);
        $this->assertDatabaseMissing('vendor_admins', ['email' => self::EMAIL]);
    }

    private function assertResponseHidesInternals(string $body): void
    {
        foreach (['SQLSTATE', 'insert into', 'password123', '$2y$', 'Docs Store'] as $leak) {
            $this->assertStringNotContainsString($leak, $body);
        }
    }
}
