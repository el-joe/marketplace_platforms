<?php

namespace Tests\Feature\Contracts;

use App\Enums\ContractKind;
use App\Jobs\NotifySellerNewInquiryJob;
use App\Models\Activity;
use App\Models\ClassifiedCategory;
use App\Models\ClassifiedInquiry;
use App\Models\ContractCountryAssignment;
use App\Models\Country;
use App\Models\CustomerContract;
use App\Services\Customer\CustomerContractService;
use App\Services\Vendor\ContractResolver;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Tests\Support\CollaborationContractFixtures;
use Tests\Support\ContractDocumentFixtures;
use Tests\Support\CountryContractFixtures;
use Tests\Support\CustomerContractFixtures;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * Requirement 7: customer purchase contracts. The contract of a classified advertisement is resolved from its
 * category (with parent inheritance) and country, must be accepted before the inquiry / purchase action, and is kept
 * apart from the seller and marketer contracts of the same section.
 */
class CustomerPurchaseContractTest extends TestCase
{
    use CollaborationContractFixtures;
    use ContractDocumentFixtures;
    use CountryContractFixtures;
    use CustomerContractFixtures;
    use RefreshDatabase;

    private MarketplaceScenario $scenario;

    private Country $home;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);
        Bus::fake([NotifySellerNewInquiryJob::class]);

        $this->scenario = MarketplaceScenario::make()->build();
        $this->home = $this->scenario->country;
        $this->home->update(['site_code' => 'ae-'.Str::lower(Str::random(5))]);
    }

    private function resolver(): ContractResolver
    {
        return app(ContractResolver::class);
    }

    private function inquire($listing, ?Country $country = null)
    {
        return $this->actingAs($this->scenario->customer, 'customer')
            ->postJson($this->contractUrl($listing, 'inquiries', $country), ['message' => 'Is it available?']);
    }

    /**
     * @return array<string, mixed>
     */
    private function signPayload(array $contract, array $overrides = []): array
    {
        return $overrides + [
            'signer_name' => 'Test Customer',
            'accept' => true,
            'template_id' => $contract['template_id'],
            'version' => $contract['version'],
            'language' => 'en',
        ];
    }

    private function sign($listing, array $contract, array $overrides = [], $customer = null)
    {
        return $this->actingAs($customer ?? $this->scenario->customer, 'customer')
            ->withHeaders(['User-Agent' => 'PHPUnit-Browser/1.0'])
            ->postJson($this->contractUrl($listing, 'customer-contract/sign'), $this->signPayload($contract, $overrides));
    }

    // ── Requirement detection ────────────────────────────────────────────────

    public function test_without_a_customer_template_nothing_changes_and_the_inquiry_goes_through(): void
    {
        $listing = $this->classifiedAd(ClassifiedCategory::factory()->create());

        $this->actingAs($this->scenario->customer, 'customer')
            ->getJson($this->contractUrl($listing, 'customer-contract'))
            ->assertOk()
            ->assertJsonPath('data.required', false)
            ->assertJsonPath('data.can_proceed', true)
            ->assertJsonPath('data.contract', null);

        $this->inquire($listing)->assertCreated();
        $this->assertNull(ClassifiedInquiry::firstOrFail()->customer_contract_id);
        $this->assertDatabaseCount('customer_contracts', 0);
    }

    public function test_a_published_template_on_the_category_makes_the_contract_required_and_renders_the_variables(): void
    {
        $category = ClassifiedCategory::factory()->create();
        $this->assignCustomerTemplate($category, $this->customerTemplate('Property purchase'));
        $listing = $this->classifiedAd($category, null, ['title_en' => 'Sea view villa']);

        $response = $this->actingAs($this->scenario->customer, 'customer')
            ->getJson($this->contractUrl($listing, 'customer-contract'))
            ->assertOk()
            ->assertJsonPath('data.required', true)
            ->assertJsonPath('data.accepted', false)
            ->assertJsonPath('data.can_proceed', false)
            ->assertJsonPath('data.contract.name', 'Property purchase')
            ->assertJsonPath('data.contract.listing.number', $listing->listing_number);

        $content = $response->json('data.contract.content_en');
        $this->assertStringContainsString('Test Customer', $content);
        $this->assertStringContainsString('Sea view villa', $content);
        $this->assertStringContainsString($this->scenario->vendor->store_name, $content);
        $this->assertStringNotContainsString('{{', $content);
    }

    public function test_draft_and_retired_templates_are_not_enforced(): void
    {
        $category = ClassifiedCategory::factory()->create();
        $draft = $this->customerTemplate('Draft one', null, ['is_published' => false, 'effective_at' => null]);
        $this->assignCustomerTemplate($category, $draft);
        $listing = $this->classifiedAd($category);

        $this->assertNull(app(CustomerContractService::class)->templateFor($listing));
        $this->inquire($listing)->assertCreated();

        $draft->update(['is_published' => true]);
        $this->assertNotNull(app(CustomerContractService::class)->templateFor($listing));

        $draft->update(['is_active' => false]);
        $this->assertNull(app(CustomerContractService::class)->templateFor($listing->fresh()));
    }

    public function test_a_sub_category_inherits_the_parent_contract_unless_it_has_its_own(): void
    {
        $parent = ClassifiedCategory::factory()->create();
        $child = ClassifiedCategory::factory()->childOf($parent)->create();
        $other = ClassifiedCategory::factory()->childOf($parent)->create();
        $parentTemplate = $this->customerTemplate('Parent contract');
        $childTemplate = $this->customerTemplate('Child contract');
        $this->assignCustomerTemplate($parent, $parentTemplate);
        $this->assignCustomerTemplate($child, $childTemplate);

        $service = app(CustomerContractService::class);

        $this->assertSame($childTemplate->id, $service->templateFor($this->classifiedAd($child))->id);
        $this->assertSame($parentTemplate->id, $service->templateFor($this->classifiedAd($other))->id);
        $this->assertSame($parentTemplate->id, $service->templateFor($this->classifiedAd($parent))->id);
    }

    public function test_the_customer_never_needs_a_contract_for_his_own_advertisement(): void
    {
        $category = ClassifiedCategory::factory()->create();
        $this->assignCustomerTemplate($category, $this->customerTemplate('Property purchase'));
        $listing = $this->classifiedAd($category, null, ['seller_type' => $this->scenario->customer::class, 'seller_id' => $this->scenario->customer->id]);

        $this->assertNull(app(CustomerContractService::class)->templateFor($listing, $this->scenario->customer));
    }

    // ── Gate: inquiry rejected until signed ──────────────────────────────────

    public function test_the_inquiry_is_rejected_with_the_contract_until_the_current_version_is_accepted(): void
    {
        $category = ClassifiedCategory::factory()->create();
        $template = $this->customerTemplate('Property purchase');
        $this->assignCustomerTemplate($category, $template);
        $listing = $this->classifiedAd($category);

        $rejected = $this->inquire($listing)
            ->assertStatus(422)
            ->assertJsonPath('code', 'customer_contract_required')
            ->assertJsonPath('data.contract.template_id', $template->id)
            ->assertJsonPath('data.contract.version', 1);
        $this->assertNotEmpty($rejected->json('data.contract.content'));
        $this->assertDatabaseCount('classified_inquiries', 0);
        Bus::assertNotDispatched(NotifySellerNewInquiryJob::class);

        $this->sign($listing, $rejected->json('data.contract'))->assertCreated()->assertJsonPath('data.accepted', true);

        $this->inquire($listing)->assertCreated();

        $inquiry = ClassifiedInquiry::firstOrFail();
        $contract = CustomerContract::firstOrFail();
        $this->assertSame($contract->id, $inquiry->customer_contract_id);
        $this->assertSame($inquiry->id, $contract->classified_inquiry_id);
        Bus::assertDispatched(NotifySellerNewInquiryJob::class);
    }

    public function test_the_gate_is_enforced_server_side_whatever_the_client_sends(): void
    {
        $category = ClassifiedCategory::factory()->create();
        $this->assignCustomerTemplate($category, $this->customerTemplate('Property purchase'));
        $listing = $this->classifiedAd($category);

        // Pretending to have accepted does not help: only a recorded acceptance of the current version counts.
        $this->actingAs($this->scenario->customer, 'customer')
            ->postJson($this->contractUrl($listing, 'inquiries'), ['message' => 'hi', 'customer_contract_id' => (string) Str::uuid(), 'accepted' => true])
            ->assertStatus(422)
            ->assertJsonPath('code', 'customer_contract_required');
    }

    public function test_signing_requires_authentication_the_acceptance_and_a_name(): void
    {
        $category = ClassifiedCategory::factory()->create();
        $template = $this->customerTemplate('Property purchase');
        $this->assignCustomerTemplate($category, $template);
        $listing = $this->classifiedAd($category);
        $contract = ['template_id' => $template->id, 'version' => 1];

        $this->postJson($this->contractUrl($listing, 'customer-contract/sign'), $this->signPayload($contract))->assertUnauthorized();
        $this->sign($listing, $contract, ['accept' => false])->assertStatus(422)->assertJsonValidationErrors('accept');
        $this->sign($listing, $contract, ['signer_name' => ''])->assertStatus(422)->assertJsonValidationErrors('signer_name');
        $this->assertDatabaseCount('customer_contracts', 0);
    }

    public function test_signing_where_no_contract_applies_is_refused(): void
    {
        $listing = $this->classifiedAd(ClassifiedCategory::factory()->create());

        $this->sign($listing, ['template_id' => (string) Str::uuid(), 'version' => 1])->assertStatus(422);
        $this->assertDatabaseCount('customer_contracts', 0);
    }

    // ── Signing record, audit and idempotency ────────────────────────────────

    public function test_the_acceptance_is_recorded_with_its_audit_fields_and_a_frozen_text(): void
    {
        $category = ClassifiedCategory::factory()->create();
        $template = $this->customerTemplate('Property purchase');
        $this->assignCustomerTemplate($category, $template);
        $listing = $this->classifiedAd($category, null, ['title_en' => 'Garden flat']);

        $this->sign($listing, ['template_id' => $template->id, 'version' => 1], ['signer_name' => 'Mr Typed Name'])->assertCreated();

        $contract = CustomerContract::firstOrFail();
        $this->assertSame($this->scenario->customer->id, $contract->customer_id);
        $this->assertSame($listing->id, $contract->classified_listing_id);
        $this->assertSame($category->id, $contract->classified_category_id);
        $this->assertSame($this->home->id, $contract->country_id);
        $this->assertSame($template->id, $contract->contract_template_id);
        $this->assertSame(1, $contract->template_version);
        $this->assertSame('purchase', $contract->purpose);
        $this->assertSame('Mr Typed Name', $contract->signer_name);
        $this->assertSame('Test Customer', $contract->customer_name);
        $this->assertSame($listing->listing_number, $contract->listing_number);
        $this->assertSame('active', $contract->status);
        $this->assertNotNull($contract->signed_at);
        $this->assertNotNull($contract->signed_ip);
        $this->assertSame('PHPUnit-Browser/1.0', $contract->signed_user_agent);
        $this->assertSame(64, strlen($contract->rendered_content_hash));
        $this->assertStringContainsString('Garden flat', $contract->rendered_content);
        $this->assertStringContainsString('Mr Typed Name', $contract->rendered_content);

        $this->assertSame(1, Activity::where('log_name', 'contracts')->where('event', 'customer_contract.signed')->count());
    }

    public function test_a_signed_record_is_immutable_but_its_status_can_change(): void
    {
        $category = ClassifiedCategory::factory()->create();
        $this->assignCustomerTemplate($category, $template = $this->customerTemplate('Property purchase'));
        $listing = $this->classifiedAd($category);
        $this->sign($listing, ['template_id' => $template->id, 'version' => 1])->assertCreated();
        $contract = CustomerContract::firstOrFail();

        $contract->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_reason' => 'Customer request']);
        $this->assertSame('cancelled', $contract->fresh()->status);

        $this->expectException(\LogicException::class);
        $contract->update(['rendered_content' => '<p>tampered</p>']);
    }

    public function test_double_submit_is_idempotent(): void
    {
        $category = ClassifiedCategory::factory()->create();
        $this->assignCustomerTemplate($category, $template = $this->customerTemplate('Property purchase'));
        $listing = $this->classifiedAd($category);
        $contract = ['template_id' => $template->id, 'version' => 1];

        $this->sign($listing, $contract)->assertCreated();
        $this->sign($listing, $contract)->assertOk();

        $this->assertDatabaseCount('customer_contracts', 1);
        $this->assertSame(1, Activity::where('event', 'customer_contract.signed')->count());
    }

    public function test_the_database_rejects_a_second_active_acceptance_of_the_same_version(): void
    {
        $category = ClassifiedCategory::factory()->create();
        $this->assignCustomerTemplate($category, $template = $this->customerTemplate('Property purchase'));
        $listing = $this->classifiedAd($category);
        $this->sign($listing, ['template_id' => $template->id, 'version' => 1])->assertCreated();
        $row = CustomerContract::firstOrFail();

        $this->expectException(UniqueConstraintViolationException::class);
        CustomerContract::create($row->only([
            'customer_id', 'contract_template_id', 'template_version', 'classified_listing_id', 'customer_name', 'language_signed',
            'content_mode', 'rendered_content', 'rendered_content_hash', 'variables', 'signer_name', 'signed_at', 'status',
        ]));
    }

    public function test_a_separate_acceptance_is_kept_per_advertisement(): void
    {
        $category = ClassifiedCategory::factory()->create();
        $this->assignCustomerTemplate($category, $template = $this->customerTemplate('Property purchase'));
        $first = $this->classifiedAd($category);
        $second = $this->classifiedAd($category);

        $this->sign($first, ['template_id' => $template->id, 'version' => 1])->assertCreated();
        $this->inquire($first)->assertCreated();
        $this->inquire($second)->assertStatus(422)->assertJsonPath('code', 'customer_contract_required');
    }

    // ── Versions ─────────────────────────────────────────────────────────────

    public function test_a_new_published_version_requires_a_new_acceptance_and_keeps_the_old_signature_intact(): void
    {
        $category = ClassifiedCategory::factory()->create();
        $v1 = $this->customerTemplate('Property purchase');
        $this->assignCustomerTemplate($category, $v1);
        $listing = $this->classifiedAd($category);

        $this->sign($listing, ['template_id' => $v1->id, 'version' => 1])->assertCreated();
        $this->inquire($listing)->assertCreated();
        $old = CustomerContract::firstOrFail();
        $frozen = [$old->rendered_content, $old->rendered_content_hash, $old->template_version];

        $v2 = $this->customerTemplate('Property purchase', null, ['version' => 2, 'is_published' => false, 'content_en' => '<p>NEW TERMS {{customer.name}}</p>']);
        $this->publishCustomerTemplate($v2);

        $this->assertFalse($v1->fresh()->is_published);
        $this->assertSame($v2->id, app(CustomerContractService::class)->templateFor($listing)->id);

        $rejected = $this->inquire($listing)->assertStatus(422)->assertJsonPath('code', 'customer_contract_required')->assertJsonPath('data.contract.version', 2);
        $this->assertStringContainsString('NEW TERMS', $rejected->json('data.contract.content_en'));

        // A client that still holds version 1 is told the contract changed instead of signing text it did not read.
        $this->sign($listing, ['template_id' => $v1->id, 'version' => 1])->assertStatus(422)->assertJsonPath('code', 'customer_contract_changed');

        $this->sign($listing, ['template_id' => $v2->id, 'version' => 2])->assertCreated();
        $this->inquire($listing)->assertCreated();

        $this->assertSame(2, CustomerContract::count());
        $old->refresh();
        $this->assertSame($frozen, [$old->rendered_content, $old->rendered_content_hash, $old->template_version]);
        $this->assertSame('superseded', $old->status);
        $this->assertSame('active', CustomerContract::where('template_version', 2)->firstOrFail()->status);
        $this->assertStringContainsString('Test Customer', $old->rendered_content);
    }

    // ── Countries ────────────────────────────────────────────────────────────

    public function test_different_countries_get_their_own_contract_and_never_another_countrys(): void
    {
        $kuwait = $this->otherCountry('KW', 'Kuwait');
        $category = ClassifiedCategory::factory()->create();
        $uaeTemplate = $this->customerTemplate('Property purchase UAE', $this->home);
        $kwTemplate = $this->customerTemplate('Property purchase KW', $kuwait);
        $this->assignCustomerTemplate($category, $uaeTemplate);
        $this->assignCustomerTemplate($category, $kwTemplate);

        $service = app(CustomerContractService::class);
        $this->assertSame($uaeTemplate->id, $service->templateFor($this->classifiedAd($category, $this->home))->id);
        $this->assertSame($kwTemplate->id, $service->templateFor($this->classifiedAd($category, $kuwait))->id);
    }

    public function test_a_country_without_a_template_gets_no_contract_and_is_never_served_another_countrys(): void
    {
        $kuwait = $this->otherCountry('KW', 'Kuwait');
        $category = ClassifiedCategory::factory()->create();
        $this->assignCustomerTemplate($category, $this->customerTemplate('Property purchase UAE', $this->home));

        $kuwaitAd = $this->classifiedAd($category, $kuwait);

        $this->assertNull(app(CustomerContractService::class)->templateFor($kuwaitAd));
        $this->assertNull($this->resolver()->resolveForCustomerPurchase($category, $kuwait));
        $this->assertNull($this->resolver()->resolveForCustomerPurchase($category, null), 'no country: only an all-countries contract can apply');
    }

    public function test_an_all_countries_template_serves_countries_without_their_own_and_yields_to_a_country_one(): void
    {
        $kuwait = $this->otherCountry('KW', 'Kuwait');
        $category = ClassifiedCategory::factory()->create();
        $legacy = $this->customerTemplate('Property purchase (all)');
        $uae = $this->customerTemplate('Property purchase UAE', $this->home);
        $this->assignCustomerTemplate($category, $legacy);
        $this->assignCustomerTemplate($category, $uae);

        $this->assertSame($uae->id, $this->resolver()->resolveForCustomerPurchase($category, $this->home)->id);
        $this->assertSame($legacy->id, $this->resolver()->resolveForCustomerPurchase($category, $kuwait)->id);
    }

    public function test_a_country_template_cannot_be_used_in_another_country_even_if_linked_to_it(): void
    {
        $kuwait = $this->otherCountry('KW', 'Kuwait');
        $category = ClassifiedCategory::factory()->create();
        $uae = $this->customerTemplate('Property purchase UAE', $this->home);
        // A corrupt link: the UAE template assigned to Kuwait.
        $this->assignCustomerTemplate($category, $uae, $kuwait);

        $this->assertNull($this->resolver()->resolveForCustomerPurchase($category, $kuwait));
    }

    // ── Distinct from seller and marketer contracts ──────────────────────────

    public function test_seller_customer_and_marketer_contracts_of_the_same_section_stay_distinct(): void
    {
        $category = ClassifiedCategory::factory()->create();
        $seller = $this->contractTemplate('classified', 'Seller section terms', $this->home);
        ContractCountryAssignment::create(['classified_category_id' => $category->id, 'country_id' => $this->home->id, 'contract_template_id' => $seller->id]);
        $category->forceFill(['requires_contract' => true])->save();
        $customer = $this->customerTemplate('Customer section terms', $this->home);
        $this->assignCustomerTemplate($category, $customer);
        $marketer = $this->collaborationTemplate('Collab terms', ['influencer_profile'], $this->home);

        $this->assertSame(ContractKind::SellerCategory, $seller->fresh()->contract_kind);
        $this->assertSame(ContractKind::CustomerPurchase, $customer->fresh()->contract_kind);
        $this->assertSame(ContractKind::SellerMarketer, $marketer->fresh()->contract_kind);
        $this->assertSame('customer', $customer->category_scope);

        $sellerResolved = $this->resolver()->resolveForCategory('classified', $category, $this->home);
        $this->assertSame($seller->id, $sellerResolved->template->id, 'the seller never receives the customer contract');
        $this->assertSame($customer->id, $this->resolver()->resolveForCustomerPurchase($category, $this->home)->id, 'the customer never receives the seller contract');

        // A section with only a seller contract does not gate customers, and one with only a customer contract does not gate sellers.
        $sellerOnly = ClassifiedCategory::factory()->create();
        ContractCountryAssignment::create(['classified_category_id' => $sellerOnly->id, 'country_id' => $this->home->id, 'contract_template_id' => $seller->id]);
        $sellerOnly->forceFill(['requires_contract' => true])->save();
        $this->assertNull($this->resolver()->resolveForCustomerPurchase($sellerOnly, $this->home));

        $customerOnly = ClassifiedCategory::factory()->create();
        $this->assignCustomerTemplate($customerOnly, $customer);
        $this->assertFalse($this->resolver()->resolveForCategory('classified', $customerOnly, $this->home)->isIndividual());
    }

    public function test_publishing_a_customer_version_does_not_touch_the_seller_template_of_the_same_name(): void
    {
        $category = ClassifiedCategory::factory()->create();
        $seller = $this->contractTemplate('classified', 'Property terms', $this->home);
        $customerV1 = $this->customerTemplate('Property terms', $this->home);
        $this->assignCustomerTemplate($category, $customerV1);
        $customerV2 = $this->customerTemplate('Property terms', $this->home, ['version' => 2, 'is_published' => false, 'effective_at' => null]);

        $this->publishCustomerTemplate($customerV2);

        $this->assertTrue($seller->fresh()->is_published, 'the seller contract of the same name keeps being served');
        $this->assertFalse($customerV1->fresh()->is_published);
        $this->assertDatabaseHas('customer_contract_assignments', ['classified_category_id' => $category->id, 'contract_template_id' => $customerV2->id]);
    }

    // ── Rental ───────────────────────────────────────────────────────────────

    public function test_a_rental_listing_is_recorded_with_the_rental_purpose(): void
    {
        $category = ClassifiedCategory::factory()->create();
        $this->assignCustomerTemplate($category, $template = $this->customerTemplate('Property purchase'));
        $listing = $this->classifiedAd($category, null, ['listing_purpose' => 'rent']);

        $this->sign($listing, ['template_id' => $template->id, 'version' => 1])->assertCreated();

        $this->assertSame('rental', CustomerContract::firstOrFail()->purpose);
    }

    // ── Web storefront ───────────────────────────────────────────────────────

    public function test_the_web_storefront_inquiry_is_gated_by_the_same_service(): void
    {
        $category = ClassifiedCategory::factory()->create();
        $this->assignCustomerTemplate($category, $template = $this->customerTemplate('Property purchase'));
        $listing = $this->classifiedAd($category);
        $customer = $this->scenario->customer;

        $this->actingAs($customer, 'customer')
            ->postJson(route('classifieds.inquire', ['country' => 'ae', 'listing' => $listing->id]), ['message' => 'Hello'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'customer_contract_required');
        $this->assertDatabaseCount('classified_inquiries', 0);

        $this->actingAs($customer, 'customer')
            ->postJson(route('classifieds.customer-contract.accept', ['country' => 'ae', 'listing' => $listing->id]), ['signer_name' => 'Web Name', 'accept' => 1, 'version' => 1])
            ->assertOk()
            ->assertJsonPath('created', true);

        $this->actingAs($customer, 'customer')
            ->postJson(route('classifieds.inquire', ['country' => 'ae', 'listing' => $listing->id]), ['message' => 'Hello'])
            ->assertOk();

        $this->assertSame(CustomerContract::firstOrFail()->id, ClassifiedInquiry::firstOrFail()->customer_contract_id);
    }

    public function test_the_web_storefront_inquiry_requires_a_logged_in_customer(): void
    {
        $listing = $this->classifiedAd(ClassifiedCategory::factory()->create());

        $this->postJson(route('classifieds.inquire', ['country' => 'ae', 'listing' => $listing->id]), ['message' => 'Hello'])->assertUnauthorized();
    }
}
