<?php

namespace Tests\Feature\Portal;

use App\Enums\VendorType;
use App\Models\Admin;
use App\Models\ClassifiedCategory;
use App\Models\ClassifiedContractTemplate;
use App\Models\ContractCountryAssignment;
use App\Models\Country;
use App\Models\Setting;
use App\Models\Vendor;
use App\Models\VendorContract;
use App\Services\Portal\JoinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * Client feedback #20/#25: the /join "Open Souq" card is now "Classifieds" (الإعلانات المبوبة). Selecting it reveals
 * one sub-option per active top-level classified category (plus travel agencies), each opening its own landing and
 * registration, and the registration shows and signs that section's contract for the seller's country.
 */
class ClassifiedsJoinTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $scenario;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->scenario = MarketplaceScenario::make()->build();
        $this->scenario->country->update(['site_code' => 'ae']);
        $this->withSession(['locale' => 'ar', 'portal_country' => 'ae']);
    }

    private function categoryWithContract(): array
    {
        $category = ClassifiedCategory::factory()->create(['name_ar' => 'عقارات', 'name_en' => 'Real Estate', 'requires_contract' => true]);
        $template = ClassifiedContractTemplate::factory()->create([
            'name' => 'Real estate contract',
            'country_id' => $this->scenario->country->id,
            'acceptance_mode' => 'click_wrap',
            'created_by_admin_id' => Admin::factory()->create()->id,
        ]);
        ContractCountryAssignment::create([
            'classified_category_id' => $category->id,
            'country_id' => $this->scenario->country->id,
            'contract_template_id' => $template->id,
        ]);

        return [$category, $template];
    }

    /**
     * @return array<string, mixed>
     */
    private function registrationSession(string $categoryId): array
    {
        $email = 'classified-'.Str::lower(Str::random(6)).'@example.test';

        return [
            'step1' => ['name' => 'Amina Seller', 'email' => $email, 'phone' => '+9689'.random_int(1000000, 9999999), 'gender' => 'female', 'country_id' => $this->scenario->country->id, 'password' => 'password123'],
            'step2' => ['store_name' => 'Store '.Str::random(6), 'store_slug' => 'store-'.Str::lower(Str::random(8)), 'business_type' => 'llc', 'business_name' => 'Amina LLC'],
            'step3' => ['contact_email' => $email, 'contact_phone' => '+96890000000', 'city_id' => $this->scenario->city->id, 'street_address' => 'Al Khuwair'],
            'documents' => [],
            'vendor_type' => VendorType::ClassifiedVendor->value,
            'classified_category_id' => $categoryId,
        ];
    }

    // ── /join ─────────────────────────────────────────────────────────────

    public function test_join_page_offers_active_top_level_categories_and_travel_under_classifieds(): void
    {
        $realEstate = ClassifiedCategory::factory()->create(['name_ar' => 'عقارات للبيع']);
        $cars = ClassifiedCategory::factory()->create(['name_ar' => 'سيارات مستعملة']);
        $inactive = ClassifiedCategory::factory()->inactive()->create(['name_ar' => 'قسم موقوف']);
        $child = ClassifiedCategory::factory()->childOf($realEstate)->create(['name_ar' => 'شقق فرعية']);
        $otherCountryOnly = ClassifiedCategory::factory()->create(['name_ar' => 'قسم دولة أخرى']);
        $otherCountryOnly->countries()->attach(Country::factory()->create(['is_active' => true])->id);

        $response = $this->get(route('portal.join'));

        $response->assertOk()
            ->assertSee('الإعلانات المبوبة')
            ->assertSee('data-testid="classified-options"', false)
            ->assertSee('data-classified-option="'.$realEstate->id.'"', false)
            ->assertSee('data-classified-option="'.$cars->id.'"', false)
            ->assertSee('data-classified-option="'.JoinService::TRAVEL_OPTION.'"', false)
            ->assertSee('وكالات السفر والسياحة')
            ->assertDontSee($inactive->name_ar)
            ->assertDontSee($child->name_ar)
            ->assertDontSee($otherCountryOnly->name_ar)
            ->assertSee('name="classified_option"', false);
    }

    public function test_join_page_shows_the_english_label(): void
    {
        $this->withSession(['locale' => 'en', 'portal_country' => 'ae'])->get(route('portal.join'))
            ->assertOk()
            ->assertSee('Classifieds')
            ->assertSee('Travel &amp; tourism agencies', false)
            ->assertDontSee('Open Souq');
    }

    public function test_travel_keeps_its_own_card_when_classifieds_is_hidden(): void
    {
        Setting::where('key', 'join_option_open_souq_enabled')->firstOrFail()->update(['value' => false]);
        Cache::flush();

        $this->get(route('portal.join'))
            ->assertOk()
            ->assertSee('وكالة سفر')
            ->assertDontSee('data-testid="classified-options"', false);
    }

    public function test_choosing_a_category_opens_its_landing_with_a_registration_link_carrying_it(): void
    {
        $category = ClassifiedCategory::factory()->create();

        $this->post(route('portal.join.select'), ['section' => 'open_souq', 'classified_option' => $category->id])
            ->assertRedirect(route('portal.companies.open-souq.category', $category->slug))
            ->assertSessionHas('join.selection.classified_category_id', $category->id);

        $this->get(route('portal.companies.open-souq.category', $category->slug))
            ->assertOk()
            ->assertSee($category->name_ar)
            ->assertSee(route('portal.register', ['account' => 'classified', 'category' => $category->slug]));
    }

    public function test_choosing_travel_under_classifieds_opens_the_travel_agency_flow(): void
    {
        $this->post(route('portal.join.select'), ['section' => 'open_souq', 'classified_option' => JoinService::TRAVEL_OPTION])
            ->assertRedirect(route('portal.companies.travel-agency'))
            ->assertSessionHas('join.selection.section', 'travel_agency');
    }

    public function test_an_inactive_category_is_rejected(): void
    {
        $inactive = ClassifiedCategory::factory()->inactive()->create();

        $this->from(route('portal.join'))
            ->post(route('portal.join.select'), ['section' => 'open_souq', 'classified_option' => $inactive->id])
            ->assertRedirect(route('portal.join'))
            ->assertSessionHasErrors('classified_option');

        $this->get(route('portal.companies.open-souq.category', $inactive->slug))->assertNotFound();
    }

    // ── Registration with the chosen section ──────────────────────────────

    public function test_registration_shows_the_chosen_sections_contract_for_the_country(): void
    {
        [$category, $template] = $this->categoryWithContract();

        $this->get(route('portal.register', ['account' => 'classified', 'category' => $category->slug]))
            ->assertOk()
            ->assertSessionHas('reg_data.classified_category_id', $category->id)
            ->assertSee('أنت تسجّل حساب «الإعلانات المبوبة» في قسم عقارات')
            ->assertSee('data-template-id="'.$template->id.'"', false)
            ->assertSee('عقد قسم عقارات');
    }

    public function test_a_product_registration_never_carries_a_classified_category(): void
    {
        [$category] = $this->categoryWithContract();

        $this->get(route('portal.register', ['category' => $category->slug]))
            ->assertOk()
            ->assertSessionMissing('reg_data.classified_category_id')
            ->assertDontSee('data-testid="category-contract"', false);
    }

    public function test_submitting_signs_the_chosen_sections_contract(): void
    {
        [$category, $template] = $this->categoryWithContract();
        $session = $this->registrationSession($category->id);

        $this->withSession(['reg_data' => $session])
            ->postJson(route('portal.register.complete'), [
                'terms_agreed' => '1',
                'privacy_agreed' => '1',
                'signature_name' => 'Amina Seller',
                'agreement_language' => 'ar',
                'category_contract_template_id' => $template->id,
                'category_contract_agreed' => '1',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $vendor = Vendor::where('email', $session['step1']['email'])->firstOrFail();
        $this->assertTrue($vendor->isClassifiedVendor());

        $contract = VendorContract::where('vendor_id', $vendor->id)->where('classified_category_id', $category->id)->firstOrFail();
        $this->assertSame($template->id, $contract->contract_template_id);
        $this->assertSame('Amina Seller', $contract->signer_name);
        $this->assertSame('active', $contract->status);
    }

    public function test_submitting_without_agreeing_to_the_sections_contract_is_refused(): void
    {
        [$category, $template] = $this->categoryWithContract();
        $session = $this->registrationSession($category->id);

        $this->withSession(['reg_data' => $session])
            ->postJson(route('portal.register.complete'), [
                'terms_agreed' => '1',
                'privacy_agreed' => '1',
                'signature_name' => 'Amina Seller',
                'agreement_language' => 'ar',
                'category_contract_template_id' => $template->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category_contract_agreed');

        $this->assertFalse(Vendor::where('email', $session['step1']['email'])->exists());
    }
}
