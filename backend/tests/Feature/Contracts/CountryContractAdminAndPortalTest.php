<?php

namespace Tests\Feature\Contracts;

use App\Models\Activity;
use App\Models\Admin;
use App\Models\Category;
use App\Models\ClassifiedCategory;
use App\Models\ClassifiedContractTemplate;
use App\Models\ContractCountryAssignment;
use App\Models\Country;
use App\Models\Product;
use App\Models\Vendor;
use App\Models\VendorContract;
use App\Services\Vendor\ContractAssignmentService;
use App\Services\Vendor\SellerAgreementService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\Support\CountryContractFixtures;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * Admin management (country, default, retire, filters, requirement switches), registration step 5 and the public
 * terms page, all per country.
 */
class CountryContractAdminAndPortalTest extends TestCase
{
    use CountryContractFixtures;
    use RefreshDatabase;

    private MarketplaceScenario $scenario;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);
        Mail::fake();

        foreach (['classifieds.view', 'vendors.view', 'vendors.assigned_only', 'categories.view', 'categories.edit', 'products.edit', 'products.view'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'admin']);
        }

        $this->scenario = MarketplaceScenario::make()->build();
        $this->scenario->vendor->update(['global_status' => 'active', 'onboarding_completed_at' => now(), 'vendor_type' => 'product_vendor']);

        $this->admin = Admin::factory()->create();
        $this->admin->givePermissionTo(['classifieds.view', 'vendors.view', 'vendors.assigned_only', 'categories.view', 'categories.edit']);
    }

    private function agreements(): SellerAgreementService
    {
        return app(SellerAgreementService::class);
    }

    private function assignments(): ContractAssignmentService
    {
        return app(ContractAssignmentService::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function templateForm(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Seller Agreement',
            'category_scope' => 'platform',
            'content_mode' => 'editor',
            'content_en' => '<p>Terms</p>',
            'content_ar' => '<p>الشروط</p>',
            'acceptance_mode' => 'click_wrap',
            'notice_days' => 14,
        ];
    }

    // ── Admin: templates ─────────────────────────────────────────────────────

    public function test_admin_creates_country_templates_with_their_own_version_lineage(): void
    {
        $oman = $this->extraCountry('OM', 'Oman');

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.contracts.templates.store'), $this->templateForm(['country_id' => $oman->id]))
            ->assertRedirect();
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.contracts.templates.store'), $this->templateForm(['country_id' => $this->scenario->country->id]))
            ->assertRedirect();
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.contracts.templates.store'), $this->templateForm())
            ->assertRedirect();

        $versions = ClassifiedContractTemplate::orderBy('created_at')->get()->map(fn ($t) => [$t->country_id, $t->version]);
        $this->assertCount(3, $versions);
        $this->assertSame([1, 1, 1], $versions->pluck(1)->all(), 'each country (and the legacy lineage) starts at version 1');
    }

    public function test_default_flag_requires_a_country_and_platform_templates_cannot_be_country_defaults(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->from(route('admin.contracts.templates.create'))
            ->post(route('admin.contracts.templates.store'), $this->templateForm(['category_scope' => 'product', 'name' => 'Food', 'is_country_default' => 1]))
            ->assertSessionHasErrors('is_country_default');

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.contracts.templates.store'), $this->templateForm(['country_id' => $this->scenario->country->id, 'is_country_default' => 1]))
            ->assertRedirect();

        $this->assertFalse(ClassifiedContractTemplate::sole()->is_country_default);
    }

    public function test_publishing_in_one_country_through_the_admin_never_retires_another_and_is_audited(): void
    {
        $uae = $this->scenario->country;
        $oman = $this->extraCountry('OM', 'Oman');
        $uaeV1 = $this->contractTemplate('platform', 'Seller Agreement', $uae);
        $omanV1 = $this->contractTemplate('platform', 'Seller Agreement', $oman);
        $uaeV2 = $this->contractTemplate('platform', 'Seller Agreement', $uae, 2, published: false);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.contracts.templates.publish', $uaeV2))
            ->assertRedirect();

        $this->assertFalse($uaeV1->fresh()->is_published);
        $this->assertTrue($uaeV2->fresh()->is_published);
        $this->assertTrue($omanV1->fresh()->is_published);

        $log = Activity::where('log_name', 'contracts')->where('event', 'contract_template.published')->sole();
        $this->assertSame($uaeV2->id, $log->subject_id);
        $this->assertSame($uae->id, $log->properties['country_id']);
    }

    public function test_retiring_a_signed_contract_needs_confirmation_keeps_signatures_and_is_audited(): void
    {
        $uae = $this->scenario->country;
        $agreement = $this->contractTemplate('platform', 'Seller Agreement', $uae);
        $signature = $this->agreements()->sign($this->scenario->vendor, null, $agreement, 'en', 'Jane', '127.0.0.1', 'phpunit');

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.contracts.templates.retire', $agreement))
            ->assertSessionHas('error');
        $this->assertTrue($agreement->fresh()->is_published, 'without confirmation nothing changes');

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.contracts.templates.retire', $agreement), ['confirm_signatures' => 1])
            ->assertSessionHas('success');

        $this->assertFalse($agreement->fresh()->is_published);
        $this->assertFalse($agreement->fresh()->is_active);
        $this->assertSame('active', $signature->fresh()->status, 'the signature stays valid');
        $this->assertSame(1, Activity::where('event', 'contract_template.retired')->count());
        $this->assertNull($this->agreements()->latestPublished($uae->id), 'no fallback to another country, and no legacy agreement exists');
    }

    public function test_country_default_can_be_changed_and_only_one_default_survives(): void
    {
        $uae = $this->scenario->country;
        $first = $this->contractTemplate('product', 'First', $uae, default: true);
        $second = $this->contractTemplate('product', 'Second', $uae);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.contracts.templates.default', $second), ['default' => 1])
            ->assertSessionHas('success');

        $this->assertFalse($first->fresh()->is_country_default);
        $this->assertTrue($second->fresh()->is_country_default);
        $this->assertSame(1, Activity::where('event', 'contract_template.country_default_changed')->count());

        $legacy = $this->contractTemplate('product', 'Legacy');
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.contracts.templates.default', $legacy), ['default' => 1])
            ->assertSessionHas('error');
    }

    public function test_index_filters_by_country_and_status_and_warns_about_countries_without_an_agreement(): void
    {
        $uae = $this->scenario->country;
        $oman = $this->extraCountry('OM', 'Oman');
        $this->vendorIn($oman);
        $this->contractTemplate('platform', 'UAE Agreement', $uae);
        $this->contractTemplate('product', 'Oman Food', $oman, published: false);
        $this->contractTemplate('product', 'Legacy Food');

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.contracts.templates.index', ['country' => $uae->id]))
            ->assertOk()
            ->assertSee('UAE Agreement')
            ->assertDontSee('Oman Food')
            ->assertDontSee('Legacy Food');

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.contracts.templates.index', ['country' => 'legacy']))
            ->assertOk()
            ->assertSee('Legacy Food')
            ->assertDontSee('UAE Agreement');

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.contracts.templates.index', ['status' => 'draft']))
            ->assertOk()
            ->assertSee('Oman Food')
            ->assertDontSee('UAE Agreement');

        // Oman has a seller and no agreement: the missing-configuration warning names it.
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.contracts.templates.index'))
            ->assertOk()
            ->assertSee(__('contracts.admin.missing_agreement_title'))
            ->assertSee('Oman');
    }

    public function test_signatures_list_filters_by_country_and_shows_legacy_signatures(): void
    {
        $uae = $this->scenario->country;
        $oman = $this->extraCountry('OM', 'Oman');
        $omani = $this->vendorIn($oman, 'Omani Store');
        $uaeAgreement = $this->contractTemplate('platform', 'UAE Agreement', $uae);
        $omanAgreement = $this->contractTemplate('platform', 'Oman Agreement', $oman);
        $this->agreements()->sign($this->scenario->vendor, null, $uaeAgreement, 'en', 'Jane', '127.0.0.1', 'phpunit');
        $this->agreements()->sign($omani, null, $omanAgreement, 'en', 'Omar', '127.0.0.1', 'phpunit');
        VendorContract::whereHas('vendor', fn ($q) => $q->where('id', $this->scenario->vendor->id))->update(['country_id' => null]);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.contracts.signatures.index', ['country' => $oman->id]))
            ->assertOk()
            ->assertSee('Omani Store')
            ->assertDontSee($this->scenario->vendor->store_name);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.contracts.signatures.index', ['country' => 'legacy']))
            ->assertOk()
            ->assertSee($this->scenario->vendor->store_name)
            ->assertDontSee('Omani Store');
    }

    // ── Admin: requirement switches ──────────────────────────────────────────

    public function test_switching_a_category_on_needs_a_usable_template_and_country_templates_must_match_their_country(): void
    {
        $uae = $this->scenario->country;
        $oman = $this->extraCountry('OM', 'Oman');
        $uaeFood = $this->contractTemplate('product', 'UAE Food', $uae);

        try {
            $this->assignments()->assertConfigurable('product', ['requires_contract' => 1], $this->scenario->category);
            $this->fail('Yes without any template must be rejected');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('requires_contract', $e->errors());
        }

        try {
            $this->assignments()->assertConfigurable('product', ['requires_contract' => 1, 'country_contracts' => [$oman->id => $uaeFood->id]]);
            $this->fail('A UAE template cannot be assigned to Oman');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('country_contracts', $e->errors());
        }

        $this->assignments()->assertConfigurable('product', ['requires_contract' => 1, 'country_contracts' => [$uae->id => $uaeFood->id]]);
        $this->assignments()->assertConfigurable('product', ['requires_contract' => 0]);
        $this->assertTrue(true);
    }

    public function test_applying_the_switch_saves_assignments_reports_gaps_and_audits(): void
    {
        $uae = $this->scenario->country;
        $oman = $this->extraCountry('OM', 'Oman');
        $this->vendorIn($oman);
        $uaeFood = $this->contractTemplate('product', 'UAE Food', $uae);
        $category = $this->scenario->category;

        $gaps = $this->assignments()->apply($category, 'product', ['requires_contract' => 1, 'country_contracts' => [$uae->id => $uaeFood->id, $oman->id => '']], $this->admin);

        $this->assertTrue($category->fresh()->requires_contract);
        $this->assertSame($uaeFood->id, ContractCountryAssignment::where('product_category_id', $category->id)->where('country_id', $uae->id)->value('contract_template_id'));
        $this->assertSame(0, ContractCountryAssignment::where('country_id', $oman->id)->count(), 'blank selection creates nothing');
        $this->assertTrue($gaps->contains('id', $oman->id), 'Oman has a seller but no contract');
        $this->assertFalse($gaps->contains('id', $uae->id));
        $this->assertSame(1, Activity::where('event', 'contract_requirement.changed')->where('subject_id', $category->id)->count());

        // Switching off keeps the configuration for later but stops enforcement.
        $this->assignments()->apply($category, 'product', ['requires_contract' => 0]);
        $this->assertFalse($category->fresh()->requires_contract);
        $this->assertSame(1, ContractCountryAssignment::where('product_category_id', $category->id)->count());
    }

    public function test_admin_category_update_saves_the_switch_and_country_templates_through_the_form(): void
    {
        $uae = $this->scenario->country;
        $uaeFood = $this->contractTemplate('product', 'UAE Food', $uae);
        $category = $this->scenario->category;

        $this->actingAs($this->admin, 'admin')
            ->putJson(route('admin.categories.update', $category->id), [
                'name_en' => 'Electronics', 'name_ar' => 'إلكترونيات', 'is_active' => 1, 'is_visible' => 1,
                'requires_contract' => 1, 'country_contracts' => [$uae->id => $uaeFood->id],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertTrue($category->fresh()->requires_contract);
        $this->assertDatabaseHas('contract_country_assignments', ['product_category_id' => $category->id, 'country_id' => $uae->id, 'contract_template_id' => $uaeFood->id]);

        $this->actingAs($this->admin, 'admin')
            ->putJson(route('admin.categories.update', $category->id), [
                'name_en' => 'Electronics', 'name_ar' => 'إلكترونيات', 'is_active' => 1, 'is_visible' => 1,
                'requires_contract' => 1, 'country_contracts' => [$uae->id => '00000000-0000-0000-0000-000000000000'],
            ])
            ->assertStatus(422);

        $other = Category::factory()->create();
        $this->actingAs($this->admin, 'admin')
            ->putJson(route('admin.categories.update', $other->id), [
                'name_en' => 'Plain', 'name_ar' => 'عادي', 'is_active' => 1, 'is_visible' => 1, 'requires_contract' => 1,
            ])
            ->assertStatus(422);
    }

    public function test_classified_category_switch_and_country_template_are_saved_by_the_admin_form(): void
    {
        $uae = $this->scenario->country;
        $cars = $this->contractTemplate('classified', 'Cars terms', $uae);
        $category = ClassifiedCategory::create(['name_en' => 'Cars', 'name_ar' => 'سيارات', 'slug' => 'cars-x', 'is_active' => true]);

        $this->actingAs($this->admin, 'admin')
            ->putJson(route('admin.classifieds.categories.update', $category), [
                'name_en' => 'Cars', 'name_ar' => 'سيارات', 'is_active' => 1, 'sort_order' => 0,
                'requires_contract' => 1, 'country_contracts' => [$uae->id => $cars->id],
            ])
            ->assertOk();

        $this->assertTrue($category->fresh()->requires_contract);
        $this->assertSame(1, ContractCountryAssignment::where('classified_category_id', $category->id)->count());

        $this->actingAs($this->admin, 'admin')
            ->putJson(route('admin.classifieds.categories.update', $category), [
                'name_en' => 'Cars', 'name_ar' => 'سيارات', 'is_active' => 1, 'sort_order' => 0, 'requires_contract' => 0,
            ])
            ->assertOk();
        $this->assertFalse($category->fresh()->requires_contract);
    }

    public function test_product_contract_requirement_is_saved_and_validated(): void
    {
        $uae = $this->scenario->country;
        $template = $this->contractTemplate('product', 'Licence', $uae);
        $product = $this->scenario->product;

        $this->assignments()->apply($product, 'product', ['contract_requirement' => 'required', 'country_contracts' => [$uae->id => $template->id]], $this->admin);
        $this->assertSame('required', $product->fresh()->contract_requirement);
        $this->assertSame(1, ContractCountryAssignment::where('product_id', $product->id)->count());

        $this->assignments()->apply($product, 'product', ['contract_requirement' => 'inherit']);
        $this->assertNull($product->fresh()->contract_requirement, 'inherit is stored as NULL');

        $this->expectException(ValidationException::class);
        $this->assignments()->assertConfigurable('product', ['contract_requirement' => 'required'], Product::factory()->create(['category_id' => $this->scenario->category->id]));
    }

    // ── Portal: registration step 5 and the public terms page ────────────────

    /**
     * @return array<string, mixed>
     */
    private function registrationSession(Country $country, string $email = 'new-seller@example.test'): array
    {
        return ['reg_data' => [
            'step1' => ['name' => 'Amina Seller', 'email' => $email, 'phone' => '+96890000000', 'gender' => 'female', 'country_id' => $country->id, 'password' => 'password123'],
            'step2' => ['store_name' => 'Amina Store', 'store_slug' => 'amina-store', 'business_type' => 'llc', 'business_name' => 'Amina LLC'],
            'step3' => ['contact_email' => $email, 'contact_phone' => '+96890000000', 'city_id' => $this->scenario->city->id, 'street_address' => 'Al Khuwair'],
            'documents' => [],
        ]];
    }

    private function complete(Country $country, array $payload = [])
    {
        return $this->withSession($this->registrationSession($country))
            ->postJson(route('portal.register.complete'), $payload + ['terms_agreed' => '1', 'privacy_agreed' => '1']);
    }

    public function test_registration_step_five_uses_the_agreement_of_the_country_chosen_in_step_one(): void
    {
        $uae = $this->scenario->country;
        $oman = $this->extraCountry('OM', 'Oman');
        $uaeAgreement = $this->contractTemplate('platform', 'Seller Agreement', $uae);
        $omanAgreement = $this->contractTemplate('platform', 'Seller Agreement', $oman);

        // The Omani applicant must sign the Omani agreement: the UAE id is rejected, the page offers both.
        $this->complete($oman, ['signature_name' => 'Omar Seller', 'agreement_language' => 'en', 'agreement_template_id' => $uaeAgreement->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('agreement_template_id');
        $this->assertDatabaseMissing('vendors', ['email' => 'new-seller@example.test']);

        $this->complete($oman, ['signature_name' => 'Omar Seller', 'agreement_language' => 'en', 'agreement_template_id' => $omanAgreement->id])
            ->assertOk();

        $vendor = Vendor::where('email', 'new-seller@example.test')->firstOrFail();
        $contract = VendorContract::where('vendor_id', $vendor->id)->sole();
        $this->assertSame($omanAgreement->id, $contract->contract_template_id);
        $this->assertSame($oman->id, $contract->country_id);

        $page = $this->get(route('portal.register'))->assertOk();
        $page->assertSee("agreementId === '{$uaeAgreement->id}'", false)
            ->assertSee("agreementId === '{$omanAgreement->id}'", false)
            ->assertSee($oman->id, false);
    }

    public function test_registration_for_a_country_without_an_agreement_is_blocked_with_a_clear_error_when_other_countries_have_one(): void
    {
        $oman = $this->extraCountry('OM', 'Oman');
        $uaeAgreement = $this->contractTemplate('platform', 'Seller Agreement', $this->scenario->country);

        $this->complete($oman, ['signature_name' => 'Omar Seller', 'agreement_language' => 'en', 'agreement_template_id' => $uaeAgreement->id])
            ->assertStatus(422)
            ->assertJsonPath('code', 'contract_not_configured')
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('vendors', ['email' => 'new-seller@example.test']);
        $this->assertSame(0, VendorContract::count());
    }

    public function test_registration_with_only_a_legacy_agreement_works_for_every_country(): void
    {
        $oman = $this->extraCountry('OM', 'Oman');
        $legacy = $this->contractTemplate('platform', 'Seller Agreement');

        $this->complete($oman, ['signature_name' => 'Omar Seller', 'agreement_language' => 'en', 'agreement_template_id' => $legacy->id])
            ->assertOk();

        $this->assertSame($legacy->id, VendorContract::sole()->contract_template_id);
    }

    public function test_public_terms_page_shows_the_requested_countrys_agreement_or_a_clear_notice(): void
    {
        $uae = $this->scenario->country;
        $oman = $this->extraCountry('OM', 'Oman');
        $this->extraCountry('KW', 'Kuwait');
        $this->contractTemplate('platform', 'UAE Seller Agreement', $uae);
        $this->contractTemplate('platform', 'Oman Seller Agreement', $oman);

        $this->get(route('portal.legal.seller-agreement', ['country' => 'omx']))
            ->assertOk()
            ->assertSee('Oman Seller Agreement')
            ->assertDontSee('UAE Seller Agreement');

        $this->get(route('portal.legal.seller-agreement', ['country' => 'kwx']))
            ->assertOk()
            ->assertDontSee('Oman Seller Agreement')
            ->assertDontSee('UAE Seller Agreement')
            ->assertSee('Kuwait');
    }

    public function test_partner_legal_page_shows_the_agreement_of_the_sellers_country(): void
    {
        $oman = $this->extraCountry('OM', 'Oman');
        $omani = $this->vendorIn($oman);
        $this->contractTemplate('platform', 'UAE Seller Agreement', $this->scenario->country);
        $this->contractTemplate('platform', 'Oman Seller Agreement', $oman);

        $this->actingAs($this->ownerFor($omani), 'vendor')
            ->get(route('partner.legal.index'))
            ->assertOk()
            ->assertSee('Oman Seller Agreement')
            ->assertDontSee('UAE Seller Agreement');
    }
}
