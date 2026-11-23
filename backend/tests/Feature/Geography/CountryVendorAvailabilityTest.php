<?php

namespace Tests\Feature\Geography;

use App\Models\Country;
use App\Models\VendorAdmin;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Tests\Support\CountryContractFixtures;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * Client item #23: vendor-facing country pickers (portal vendor / travel-agency registration, partner
 * "add listing", marketer sign-up) offer launched countries plus drafts the admin explicitly opened with
 * "accept vendor registrations / listings before launch" — never plain drafts or hidden countries — and
 * the server rejects any other country id.
 */
class CountryVendorAvailabilityTest extends TestCase
{
    use CountryContractFixtures;
    use RefreshDatabase;

    private MarketplaceScenario $scenario;

    private Country $live;

    private Country $draft;

    private Country $openDraft;

    private Country $hidden;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->scenario = MarketplaceScenario::make()->build();
        $this->live = $this->scenario->country;
        $this->draft = Country::factory()->create(['is_active' => true, 'is_launched' => false]);
        $this->openDraft = Country::factory()->create(['is_active' => true, 'is_launched' => false, 'accepts_vendors_before_launch' => true]);
        $this->hidden = Country::factory()->create(['is_active' => false, 'is_launched' => true, 'accepts_vendors_before_launch' => true]);
    }

    /**
     * @param  Collection<int, Country>  $countries
     */
    private function assertOffersOnlyVendorCountries(Collection $countries): void
    {
        $ids = $countries->pluck('id');

        $this->assertTrue($ids->contains($this->live->id), 'launched country missing');
        $this->assertTrue($ids->contains($this->openDraft->id), 'draft open to vendors missing');
        $this->assertFalse($ids->contains($this->draft->id), 'plain draft offered');
        $this->assertFalse($ids->contains($this->hidden->id), 'hidden country offered');
    }

    private function partnerOwner(): VendorAdmin
    {
        $owner = $this->ownerFor($this->vendorIn($this->live));
        foreach (['listings.view', 'listings.create'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'vendor']);
            $owner->givePermissionTo($permission);
        }

        return $owner;
    }

    public function test_portal_vendor_registration_lists_only_countries_open_to_vendors(): void
    {
        $this->get(route('portal.register'))
            ->assertOk()
            ->assertViewHas('countries', function (Collection $countries): bool {
                $this->assertOffersOnlyVendorCountries($countries);

                return true;
            });
    }

    public function test_portal_vendor_registration_step_one_rejects_a_draft_and_accepts_an_open_draft(): void
    {
        $payload = [
            'name' => 'Draft Seller',
            'email' => 'draft-seller@example.test',
            'phone' => '+96890001234',
            'gender' => 'male',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $this->postJson(route('portal.register.step', ['step' => 1]), $payload + ['country_id' => $this->draft->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['country_id' => __('validation.country_not_available_for_vendors')]);

        $this->postJson(route('portal.register.step', ['step' => 1]), $payload + ['country_id' => $this->hidden->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('country_id');

        $this->postJson(route('portal.register.step', ['step' => 1]), $payload + ['country_id' => $this->openDraft->id])
            ->assertJsonMissingValidationErrors('country_id');
    }

    public function test_travel_agency_registration_lists_and_accepts_only_countries_open_to_vendors(): void
    {
        $this->get(route('portal.companies.travel-agency.register.show'))
            ->assertOk()
            ->assertViewHas('countries', function (Collection $countries): bool {
                $this->assertOffersOnlyVendorCountries($countries);

                return true;
            });

        $this->postJson(route('portal.companies.travel-agency.register.step', ['step' => 1]), [
            'name' => 'Agency Owner',
            'email' => 'agency-owner@example.test',
            'phone' => '+971500000001',
            'country_id' => $this->draft->id,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertUnprocessable()->assertJsonValidationErrors('country_id');
    }

    public function test_partner_add_listing_offers_only_countries_open_to_vendors(): void
    {
        $this->actingAs($this->partnerOwner(), 'vendor')
            ->get(route('partner.listings.create'))
            ->assertOk()
            ->assertViewHas('countries', function (Collection $countries): bool {
                $this->assertOffersOnlyVendorCountries($countries);

                return true;
            });
    }

    public function test_partner_listing_store_rejects_a_country_not_open_to_vendors(): void
    {
        $this->actingAs($this->partnerOwner(), 'vendor')
            ->postJson(route('partner.listings.store'), [
                'product_variant_id' => $this->scenario->variants[0]->id,
                'country_id' => $this->draft->id,
                'price' => '150.50',
                'condition' => 'new',
                'warehouse_id' => $this->scenario->platformWarehouse->id,
                'initial_quantity' => 3,
                'declared_weight_grams' => 500,
                'handling_class' => 'standard',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('country_id');
    }

    public function test_marketer_registration_lists_and_accepts_only_countries_open_to_vendors(): void
    {
        $this->get(route('marketer.register'))
            ->assertOk()
            ->assertViewHas('countries', function (Collection $countries): bool {
                $this->assertOffersOnlyVendorCountries($countries);

                return true;
            });

        $this->post(route('marketer.register.post'), [
            'name' => 'Draft Marketer',
            'email' => 'draft-marketer@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'marketer_type' => 'influencer',
            'country_id' => $this->hidden->id,
            'gender' => 'male',
        ])->assertSessionHasErrors('country_id');
    }
}
