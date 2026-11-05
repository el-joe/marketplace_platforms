<?php

namespace Tests\Feature\Contracts;

use App\Enums\ContractKind;
use App\Models\Activity;
use App\Models\Admin;
use App\Models\ClassifiedCategory;
use App\Models\ClassifiedContractTemplate;
use App\Models\Country;
use App\Models\CustomerContract;
use App\Services\Customer\CustomerContractService;
use Database\Seeders\ContractTemplateSeeder;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\ContractDocumentFixtures;
use Tests\Support\CountryContractFixtures;
use Tests\Support\CustomerContractFixtures;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * Requirement 7 (admin): customer purchase templates inside the contract templates screens, and the signed
 * customer contracts list with filters, detail and permission protection.
 */
class CustomerContractAdminTest extends TestCase
{
    use ContractDocumentFixtures;
    use CountryContractFixtures;
    use CustomerContractFixtures;
    use RefreshDatabase;

    private MarketplaceScenario $scenario;

    private Country $home;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);

        foreach (['classifieds.view', 'vendors.view', 'vendors.assigned_only'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'admin']);
        }

        $this->scenario = MarketplaceScenario::make()->build();
        $this->home = $this->scenario->country;
        $this->admin = Admin::factory()->create();
        $this->admin->givePermissionTo('classifieds.view');
    }

    /**
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Property purchase',
            'category_scope' => 'customer',
            'content_mode' => 'editor',
            'content_en' => '<p>Terms for {{customer.name}}</p>',
            'content_ar' => '<p>شروط</p>',
            'sections_submitted' => 1,
        ];
    }

    private function createTemplate(array $overrides = []): ClassifiedContractTemplate
    {
        $form = $this->form($overrides);

        $this->actingAs($this->admin, 'admin')->post(route('admin.contracts.templates.store'), $form)->assertRedirect()->assertSessionHasNoErrors();

        return ClassifiedContractTemplate::where('name', $form['name'])->orderByDesc('version')->firstOrFail();
    }

    private function signed(array $overrides = []): CustomerContract
    {
        $category = $overrides['category'] ?? ClassifiedCategory::factory()->create();
        unset($overrides['category']);
        $template = $overrides['template'] ?? $this->customerTemplate('Template '.Str::random(4));
        unset($overrides['template']);

        $country = $overrides['country'] ?? null;
        unset($overrides['country']);

        return CustomerContract::factory()->forListing($this->classifiedAd($category, $country))->create($overrides + [
            'customer_id' => $this->scenario->customer->id,
            'contract_template_id' => $template->id,
            'customer_name' => $this->scenario->customer->name,
            'customer_email' => $this->scenario->customer->email,
        ]);
    }

    // ── Template management ──────────────────────────────────────────────────

    public function test_an_admin_creates_a_customer_template_linked_to_sections_and_publishing_puts_it_in_force(): void
    {
        $category = ClassifiedCategory::factory()->create();

        $template = $this->createTemplate(['country_id' => $this->home->id, 'classified_category_ids' => [$category->id], 'is_country_default' => 1]);

        $this->assertSame('customer', $template->category_scope);
        $this->assertSame(ContractKind::CustomerPurchase, $template->contract_kind);
        $this->assertFalse($template->is_country_default, 'a customer contract is never a country default');
        $this->assertDatabaseHas('customer_contract_assignments', ['classified_category_id' => $category->id, 'country_id' => $this->home->id, 'contract_template_id' => $template->id]);
        $this->assertDatabaseMissing('contract_country_assignments', ['classified_category_id' => $category->id]);
        $this->assertNull($category->fresh()->contract_template_id);

        $listing = $this->classifiedAd($category);
        $this->assertNull(app(CustomerContractService::class)->templateFor($listing), 'a draft is inert');

        $this->actingAs($this->admin, 'admin')->post(route('admin.contracts.templates.publish', $template))->assertRedirect();

        $this->assertTrue($template->fresh()->is_published);
        $this->assertSame($template->id, app(CustomerContractService::class)->templateFor($listing)->id);
        $this->assertSame(1, Activity::where('event', 'contract_template.published')->count());
        $this->assertSame(1, Activity::where('event', 'customer_contract_template.sections_changed')->count());
    }

    public function test_editing_a_published_customer_template_creates_a_new_version_whose_publishing_moves_the_sections(): void
    {
        $category = ClassifiedCategory::factory()->create();
        $v1 = $this->createTemplate(['classified_category_ids' => [$category->id]]);
        $this->actingAs($this->admin, 'admin')->post(route('admin.contracts.templates.publish', $v1))->assertRedirect();

        $this->actingAs($this->admin, 'admin')
            ->put(route('admin.contracts.templates.update', $v1), $this->form(['content_en' => '<p>Changed terms</p>', 'classified_category_ids' => [$category->id]]))
            ->assertRedirect();
        $v2 = ClassifiedContractTemplate::where('name', 'Property purchase')->where('version', 2)->firstOrFail();
        $this->assertFalse($v2->is_published);

        $this->actingAs($this->admin, 'admin')->post(route('admin.contracts.templates.publish', $v2))->assertRedirect();

        $this->assertFalse($v1->fresh()->is_published);
        $this->assertTrue($v2->fresh()->is_published);
        $this->assertDatabaseHas('customer_contract_assignments', ['classified_category_id' => $category->id, 'contract_template_id' => $v2->id]);
        $this->assertDatabaseCount('customer_contract_assignments', 1);
    }

    public function test_a_section_cannot_hold_two_customer_contracts_for_the_same_country(): void
    {
        $category = ClassifiedCategory::factory()->create();
        $this->createTemplate(['name' => 'First', 'country_id' => $this->home->id, 'classified_category_ids' => [$category->id]]);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.contracts.templates.store'), $this->form(['name' => 'Second', 'country_id' => $this->home->id, 'classified_category_ids' => [$category->id]]))
            ->assertSessionHasErrors('classified_category_ids');

        $this->assertDatabaseMissing('classified_contract_templates', ['name' => 'Second']);
    }

    public function test_the_templates_screen_filters_by_kind_and_shows_the_customer_type_and_sections(): void
    {
        $category = ClassifiedCategory::factory()->create(['name_en' => 'Real Estate Section', 'name_ar' => 'Real Estate Section']);
        $customer = $this->createTemplate(['name' => 'Customer deal', 'classified_category_ids' => [$category->id]]);
        $seller = $this->contractTemplate('classified', 'Seller deal', $this->home);

        $html = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.contracts.templates.index', ['kind' => 'customer_purchase']))
            ->assertOk()
            ->assertSee('Customer deal')
            ->assertDontSee('Seller deal')
            ->assertSee('Real Estate Section')
            ->getContent();
        $this->assertStringContainsString('customer_purchase', $html);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.contracts.templates.index', ['scope' => 'customer', 'section' => $category->id]))
            ->assertOk()
            ->assertSee('Customer deal');

        $this->actingAs($this->admin, 'admin')->get(route('admin.contracts.templates.create'))->assertOk()->assertSee('value="customer"', false);
        $this->assertNotNull($seller);
    }

    public function test_retiring_a_customer_template_with_acceptances_needs_confirmation_and_keeps_them(): void
    {
        $template = $this->customerTemplate('Property purchase');
        $contract = $this->signed(['template' => $template]);

        $this->actingAs($this->admin, 'admin')->post(route('admin.contracts.templates.retire', $template))->assertSessionHas('error');
        $this->assertTrue($template->fresh()->is_published);

        $this->actingAs($this->admin, 'admin')->post(route('admin.contracts.templates.retire', $template), ['confirm_signatures' => 1])->assertRedirect();
        $this->assertFalse($template->fresh()->is_published);
        $this->assertSame('active', $contract->fresh()->status);

        $this->actingAs($this->admin, 'admin')->delete(route('admin.contracts.templates.destroy', $template))->assertSessionHas('error');
        $this->assertNotNull(ClassifiedContractTemplate::find($template->id));
    }

    public function test_the_signatures_page_of_a_customer_template_lists_its_acceptances(): void
    {
        $template = $this->customerTemplate('Property purchase');
        $this->signed(['template' => $template, 'customer_name' => 'Listed Customer']);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.contracts.templates.signatures', $template))
            ->assertOk()
            ->assertSee('Listed Customer');
    }

    // ── Signed contracts list ────────────────────────────────────────────────

    public function test_the_list_is_permission_protected(): void
    {
        $this->get(route('admin.contracts.customer.index'))->assertRedirect();

        $nobody = Admin::factory()->create();
        $this->actingAs($nobody, 'admin')->get(route('admin.contracts.customer.index'))->assertForbidden();

        $contract = $this->signed();
        $this->actingAs($nobody, 'admin')->get(route('admin.contracts.customer.show', $contract))->assertForbidden();
        $this->actingAs($nobody, 'admin')->get(route('admin.contracts.customer.file', $contract))->assertForbidden();

        $this->actingAs($this->admin, 'admin')->get(route('admin.contracts.customer.index'))->assertOk();
    }

    public function test_the_list_filters_by_country_category_status_date_and_search(): void
    {
        $kuwait = $this->extraCountry('KW', 'Kuwait');
        $realEstate = ClassifiedCategory::factory()->create();
        $villas = ClassifiedCategory::factory()->childOf($realEstate)->create();
        $cars = ClassifiedCategory::factory()->create();

        $a = $this->signed(['category' => $villas, 'customer_name' => 'Alice Villa', 'signed_at' => now()->subDays(10)]);
        $b = $this->signed(['category' => $cars, 'country' => $kuwait, 'customer_name' => 'Bob Car', 'signed_at' => now()->subDays(2)]);
        $c = $this->signed(['category' => $cars, 'customer_name' => 'Carol Old', 'status' => 'superseded', 'signed_at' => now()->subDays(1)]);

        $ids = fn (array $query) => collect($this->actingAs($this->admin, 'admin')->get(route('admin.contracts.customer.index', $query))->assertOk()->viewData('contracts')->items())->pluck('id')->sort()->values()->all();
        $expect = fn (array $contracts) => collect($contracts)->pluck('id')->sort()->values()->all();

        $this->assertSame($expect([$a, $b, $c]), $ids([]));
        $this->assertSame($expect([$b]), $ids(['country' => $kuwait->id]));
        $this->assertSame($expect([$a]), $ids(['category' => $realEstate->id]), 'a section includes its sub-sections');
        $this->assertSame($expect([$a]), $ids(['category' => $villas->id]));
        $this->assertSame($expect([$b, $c]), $ids(['category' => $cars->id]));
        $this->assertSame($expect([$c]), $ids(['status' => 'superseded']));
        $this->assertSame($expect([$b, $c]), $ids(['from' => now()->subDays(3)->toDateString()]));
        $this->assertSame($expect([$a]), $ids(['to' => now()->subDays(5)->toDateString()]));
        $this->assertSame($expect([$b]), $ids(['q' => 'Bob']));
        $this->assertSame($expect([$a]), $ids(['q' => $a->listing_number]));
        $this->assertSame($expect([$a, $b, $c]), $ids(['country' => 'garbage', 'status' => 'nonsense']), 'invalid filter values are ignored');
    }

    public function test_the_list_is_paginated(): void
    {
        $template = $this->customerTemplate('Property purchase');
        $category = ClassifiedCategory::factory()->create();
        foreach (range(1, 32) as $i) {
            $this->signed(['template' => $template, 'category' => $category]);
        }

        $page = $this->actingAs($this->admin, 'admin')->get(route('admin.contracts.customer.index'))->assertOk()->viewData('contracts');
        $this->assertSame(32, $page->total());
        $this->assertCount(30, $page->items());
    }

    public function test_the_detail_shows_the_frozen_text_and_audit_data(): void
    {
        $contract = $this->signed([
            'rendered_content' => '<p>FROZEN CUSTOMER TEXT</p>', 'signer_name' => 'Typed Signer', 'signed_ip' => '203.0.113.9',
            'signed_user_agent' => 'Mozilla/5.0 Test',
        ]);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.contracts.customer.show', $contract))
            ->assertOk()
            ->assertSee('FROZEN CUSTOMER TEXT', false)
            ->assertSee('Typed Signer')
            ->assertSee('203.0.113.9')
            ->assertSee($contract->listing_number)
            ->assertSee($contract->rendered_content_hash);
    }

    public function test_the_signed_document_download_is_permission_protected_and_audited(): void
    {
        $this->fakePrivateDisk();
        Storage::disk('private')->put('customer-contracts/x/signed.pdf', 'PDF-BYTES');
        $contract = $this->signed([
            'content_mode' => 'file', 'signed_file_path' => 'customer-contracts/x/signed.pdf', 'signed_file_name' => 'signed.pdf', 'signed_file_mime' => 'application/pdf',
        ]);

        $this->actingAs($this->admin, 'admin')->get(route('admin.contracts.customer.file', $contract))->assertOk();

        $this->assertSame(1, Activity::where('event', 'customer_contract.file_downloaded')->count());
    }

    // ── Seeder ───────────────────────────────────────────────────────────────

    public function test_the_seeder_adds_unpublished_unlinked_sample_customer_templates_once(): void
    {
        $this->seed(ContractTemplateSeeder::class);
        $this->seed(ContractTemplateSeeder::class);

        $samples = ClassifiedContractTemplate::where('category_scope', 'customer')->get();
        $this->assertCount(2, $samples);
        $this->assertTrue($samples->every(fn ($t) => ! $t->is_published && $t->version === 1));
        $this->assertDatabaseCount('customer_contract_assignments', 0);
    }
}
