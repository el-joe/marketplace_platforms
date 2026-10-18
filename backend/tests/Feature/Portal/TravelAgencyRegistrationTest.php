<?php

namespace Tests\Feature\Portal;

use App\Models\Country;
use App\Models\TravelAgency;
use App\Models\TravelAgencyMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TravelAgencyRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The portal is Arabic-first; render copy and labels in Arabic.
        $this->withSession(['locale' => 'ar']);
    }

    private function activeCountry(): Country
    {
        return Country::factory()->create(['is_active' => true]);
    }

    /**
     * @return array<string, string>
     */
    private function stepOnePayload(Country $country): array
    {
        return [
            'name' => 'أمينة الفارسي',
            'email' => 'owner@example.com',
            'phone' => '+971500000000',
            'country_id' => $country->id,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];
    }

    private function stepOneUrl(): string
    {
        return route('portal.companies.travel-agency.register.step', ['step' => 1]);
    }

    private function stepTwoUrl(): string
    {
        return route('portal.companies.travel-agency.register.step', ['step' => 2]);
    }

    public function test_travel_agency_landing_page_renders_and_links_to_registration(): void
    {
        $response = $this->get(route('portal.companies.travel-agency'));

        $response->assertOk();
        $response->assertSee(route('portal.companies.travel-agency.register.show'), false);
    }

    public function test_registration_wizard_renders_the_alpine_component(): void
    {
        $this->activeCountry();

        $response = $this->get(route('portal.companies.travel-agency.register.show'));

        $response->assertOk();
        $response->assertSee('travelAgencyWizard()', false);
    }

    public function test_step_one_stores_account_data_and_advances(): void
    {
        $country = $this->activeCountry();

        $response = $this->postJson($this->stepOneUrl(), $this->stepOnePayload($country));

        $response->assertOk()->assertJson(['success' => true, 'next_step' => 2]);
        $this->assertSame('owner@example.com', session('travel_reg_data.step1.email'));
        $this->assertSame(2, session('travel_reg_data.current_step'));
    }

    public function test_step_one_rejects_an_email_already_registered(): void
    {
        $country = $this->activeCountry();

        TravelAgency::create([
            'name' => 'Existing Agency',
            'email' => 'owner@example.com',
            'phone' => '+971500000000',
            'password' => 'password123',
            'country_id' => $country->id,
        ]);

        $response = $this->postJson($this->stepOneUrl(), $this->stepOnePayload($country));

        $response->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_full_registration_creates_a_pending_agency_and_owner_member(): void
    {
        $country = $this->activeCountry();

        $this->postJson($this->stepOneUrl(), $this->stepOnePayload($country))
            ->assertOk();

        $this->postJson($this->stepTwoUrl(), [
            'agency_name' => 'آفاق الخليج للسفر',
            'license_number' => 'DTCM-2024-0011',
        ])->assertOk()->assertJson(['success' => true, 'next_step' => 3]);

        $response = $this->postJson(route('portal.companies.travel-agency.register.complete'), [
            'terms_agreed' => true,
            'privacy_agreed' => true,
        ]);

        $response->assertOk()->assertJson([
            'success' => true,
            'redirect' => route('portal.companies.travel-agency.register.success'),
        ]);

        $this->assertDatabaseHas('travel_agencies', [
            'email' => 'owner@example.com',
            'name' => 'آفاق الخليج للسفر',
            'license_number' => 'DTCM-2024-0011',
            'country_id' => $country->id,
            'status' => 'pending',
        ]);

        $agency = TravelAgency::where('email', 'owner@example.com')->firstOrFail();

        $this->assertDatabaseHas('travel_agency_members', [
            'travel_agency_id' => $agency->id,
            'email' => 'owner@example.com',
            'role' => 'travel_agency_owner',
            'is_owner' => 1,
        ]);

        $this->assertSame(1, TravelAgencyMember::where('travel_agency_id', $agency->id)
            ->where('is_owner', true)
            ->count());
    }

    public function test_complete_requires_the_terms_to_be_accepted(): void
    {
        $response = $this->postJson(route('portal.companies.travel-agency.register.complete'), []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['terms_agreed', 'privacy_agreed']);
    }
}
