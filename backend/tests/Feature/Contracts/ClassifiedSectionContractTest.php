<?php

namespace Tests\Feature\Contracts;

use App\Enums\ClassifiedListingStatus;
use App\Enums\VendorType;
use App\Exceptions\ContractNotConfiguredException;
use App\Models\Admin;
use App\Models\ClassifiedCategory;
use App\Models\ClassifiedContractTemplate;
use App\Models\ContractCountryAssignment;
use App\Models\VendorAdmin;
use App\Models\VendorContract;
use App\Services\Shared\ClassifiedListingService;
use App\Services\Vendor\CategoryContractService;
use App\Services\Vendor\ClassifiedSectionContractService;
use App\Services\Vendor\ContractResolver;
use Database\Seeders\ContractTemplateSeeder;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\Support\CountryContractFixtures;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * Requirement 5: a classified contract is associated with the sections (classified categories) it applies to
 * through the same rows the ContractResolver reads, and the contract of an advertisement is derived from its
 * category, with parent inheritance, per country and without mixing contracts across sections.
 */
class ClassifiedSectionContractTest extends TestCase
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

        foreach (['classifieds.view', 'vendors.view', 'vendors.assigned_only', 'categories.view', 'categories.edit'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'admin']);
        }

        $this->scenario = MarketplaceScenario::make()->build();
        $this->scenario->vendor->update(['global_status' => 'active', 'onboarding_completed_at' => now()]);

        $this->admin = Admin::factory()->create();
        $this->admin->givePermissionTo(['classifieds.view', 'vendors.view', 'vendors.assigned_only', 'categories.view', 'categories.edit']);
    }

    private function resolver(): ContractResolver
    {
        return app(ContractResolver::class);
    }

    private function sections(): ClassifiedSectionContractService
    {
        return app(ClassifiedSectionContractService::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Real estate terms',
            'category_scope' => 'classified',
            'content_mode' => 'editor',
            'content_en' => '<p>Terms</p>',
            'content_ar' => '<p>الشروط</p>',
            'sections_submitted' => 1,
        ];
    }

    private function create(array $overrides = []): ClassifiedContractTemplate
    {
        $form = $this->form($overrides);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.contracts.templates.store'), $form)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        return ClassifiedContractTemplate::where('name', $form['name'])->orderByDesc('version')->firstOrFail();
    }

    private function publish(ClassifiedContractTemplate $template): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.contracts.templates.publish', $template))
            ->assertRedirect();
    }

    // ── Template form: auto-association ──────────────────────────────────────

    public function test_saving_a_country_template_links_the_selected_sections_and_publishing_makes_them_require_it(): void
    {
        $uae = $this->scenario->country;
        $realEstate = ClassifiedCategory::factory()->create();
        $vehicles = ClassifiedCategory::factory()->create();

        $template = $this->create(['country_id' => $uae->id, 'classified_category_ids' => [$realEstate->id]]);

        $this->assertDatabaseHas('contract_country_assignments', [
            'classified_category_id' => $realEstate->id, 'country_id' => $uae->id, 'contract_template_id' => $template->id,
        ]);
        $this->assertNotTrue($realEstate->fresh()->requires_contract, 'a draft never makes sellers sign');
        $this->assertFalse($this->resolver()->resolveForCategory('classified', $realEstate, $uae)->isIndividual());

        $this->publish($template);

        $this->assertTrue($realEstate->fresh()->requires_contract);
        $resolved = $this->resolver()->resolveForCategory('classified', $realEstate->fresh(), $uae);
        $this->assertTrue($resolved->isIndividual());
        $this->assertSame($template->id, $resolved->template->id);
        $this->assertFalse($this->resolver()->resolveForCategory('classified', $vehicles, $uae)->isIndividual(), 'an unrelated section is not touched');
    }

    public function test_a_country_agnostic_template_is_linked_through_the_legacy_category_column(): void
    {
        $category = ClassifiedCategory::factory()->create();

        $template = $this->create(['classified_category_ids' => [$category->id]]);
        $this->publish($template);

        $this->assertSame($template->id, $category->fresh()->contract_template_id);
        $this->assertTrue($category->fresh()->requires_contract);
        $this->assertSame($template->id, $this->resolver()->resolveForCategory('classified', $category->fresh(), $this->scenario->country)->template->id);
    }

    public function test_sub_sections_inherit_the_parent_contract_unless_they_have_their_own(): void
    {
        $uae = $this->scenario->country;
        $parent = ClassifiedCategory::factory()->create();
        $child = ClassifiedCategory::factory()->childOf($parent)->create();
        $other = ClassifiedCategory::factory()->childOf($parent)->create();

        $general = $this->create(['country_id' => $uae->id, 'classified_category_ids' => [$parent->id]]);
        $this->publish($general);

        $own = $this->create(['name' => 'Apartments terms', 'country_id' => $uae->id, 'classified_category_ids' => [$child->id]]);
        $this->publish($own);

        $this->assertSame($general->id, $this->resolver()->resolveForCategory('classified', $other->fresh(), $uae)->template->id);
        $this->assertSame($own->id, $this->resolver()->resolveForCategory('classified', $child->fresh(), $uae)->template->id);
        $this->assertSame($parent->id, $this->resolver()->resolveForCategory('classified', $other->fresh(), $uae)->subject->id, 'one signature on the parent covers the sub-section');
    }

    public function test_contracts_are_country_specific_and_never_borrowed_from_another_country(): void
    {
        $uae = $this->scenario->country;
        $oman = $this->extraCountry('OM', 'Oman');
        $category = ClassifiedCategory::factory()->create();

        $uaeTemplate = $this->create(['country_id' => $uae->id, 'classified_category_ids' => [$category->id]]);
        $this->publish($uaeTemplate);

        try {
            $this->resolver()->resolveForCategory('classified', $category->fresh(), $oman);
            $this->fail('Oman must not fall back to the UAE contract.');
        } catch (ContractNotConfiguredException $e) {
            $this->assertSame($oman->id, $e->country?->id);
        }

        $omanTemplate = $this->create(['name' => 'Real estate terms OM', 'country_id' => $oman->id, 'classified_category_ids' => [$category->id]]);
        $this->publish($omanTemplate);

        $this->assertSame($omanTemplate->id, $this->resolver()->resolveForCategory('classified', $category->fresh(), $oman)->template->id);
        $this->assertSame($uaeTemplate->id, $this->resolver()->resolveForCategory('classified', $category->fresh(), $uae)->template->id);
    }

    // ── Conflicts and unrelated contracts ────────────────────────────────────

    public function test_a_section_cannot_hold_two_active_contracts_for_the_same_country(): void
    {
        $uae = $this->scenario->country;
        $oman = $this->extraCountry('OM', 'Oman');
        $category = ClassifiedCategory::factory()->create();
        $free = ClassifiedCategory::factory()->create();

        $first = $this->create(['country_id' => $uae->id, 'classified_category_ids' => [$category->id]]);
        $this->publish($first);

        $this->actingAs($this->admin, 'admin')
            ->from(route('admin.contracts.templates.create'))
            ->post(route('admin.contracts.templates.store'), $this->form([
                'name' => 'Competing terms', 'country_id' => $uae->id, 'classified_category_ids' => [$free->id, $category->id],
            ]))
            ->assertRedirect(route('admin.contracts.templates.create'))
            ->assertSessionHasErrors('classified_category_ids');

        $this->assertSame(1, ClassifiedContractTemplate::count(), 'the whole save is rolled back');
        $this->assertSame(1, ContractCountryAssignment::count());
        $this->assertSame($first->id, ContractCountryAssignment::first()->contract_template_id);

        // Another country and the country-agnostic contract are separate slots.
        $this->create(['name' => 'Oman terms', 'country_id' => $oman->id, 'classified_category_ids' => [$category->id]]);
        $this->create(['name' => 'Legacy terms', 'classified_category_ids' => [$category->id]]);
        $this->assertSame(2, ContractCountryAssignment::count());
        $this->assertNotNull($category->fresh()->contract_template_id);
    }

    public function test_a_retired_contract_releases_its_sections(): void
    {
        $uae = $this->scenario->country;
        $category = ClassifiedCategory::factory()->create();

        $first = $this->create(['country_id' => $uae->id, 'classified_category_ids' => [$category->id]]);
        $this->publish($first);
        $this->actingAs($this->admin, 'admin')->post(route('admin.contracts.templates.retire', $first))->assertRedirect();

        $second = $this->create(['name' => 'Replacement terms', 'country_id' => $uae->id, 'classified_category_ids' => [$category->id]]);

        $this->assertSame($second->id, ContractCountryAssignment::sole()->contract_template_id);
    }

    public function test_unrelated_sections_never_share_a_contract(): void
    {
        $uae = $this->scenario->country;
        $a = ClassifiedCategory::factory()->create();
        $b = ClassifiedCategory::factory()->create();
        $template = $this->create(['country_id' => $uae->id, 'classified_category_ids' => [$a->id]]);
        $this->publish($template);

        $this->assertSame([$a->id], $this->sections()->categoryIdsFor($template));
        $this->assertNull(ContractCountryAssignment::where('classified_category_id', $b->id)->first());
        $this->assertFalse($this->resolver()->resolveForCategory('classified', $b->fresh(), $uae)->isIndividual());
    }

    public function test_the_selector_rejects_unknown_sections_and_ignores_them_for_other_scopes(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->from(route('admin.contracts.templates.create'))
            ->post(route('admin.contracts.templates.store'), $this->form(['classified_category_ids' => ['00000000-0000-4000-8000-000000000000']]))
            ->assertSessionHasErrors('classified_category_ids.0');

        $category = ClassifiedCategory::factory()->create();
        $this->create(['name' => 'Food terms', 'category_scope' => 'product', 'classified_category_ids' => [$category->id]]);
        $this->assertSame(0, ContractCountryAssignment::count());
        $this->assertNull($category->fresh()->contract_template_id);
    }

    // ── Editing, category form sync, deleting ────────────────────────────────

    public function test_editing_the_selection_adds_and_removes_links_and_switches_off_unused_sections(): void
    {
        $uae = $this->scenario->country;
        $keep = ClassifiedCategory::factory()->create();
        $drop = ClassifiedCategory::factory()->create();
        $template = $this->create(['country_id' => $uae->id, 'classified_category_ids' => [$keep->id, $drop->id]]);
        $this->publish($template);
        $this->assertTrue($drop->fresh()->requires_contract);

        $this->actingAs($this->admin, 'admin')
            ->put(route('admin.contracts.templates.update', $template), $this->form([
                'content_en' => $template->content_en, 'content_ar' => $template->content_ar,
                'name' => $template->name, 'classified_category_ids' => [$keep->id],
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame([$keep->id], $this->sections()->categoryIdsFor($template->fresh()));
        $this->assertFalse($drop->fresh()->requires_contract, 'a section left without any contract no longer requires one');
        $this->assertTrue($keep->fresh()->requires_contract);

        $this->actingAs($this->admin, 'admin')
            ->put(route('admin.contracts.templates.update', $template), $this->form([
                'content_en' => $template->content_en, 'content_ar' => $template->content_ar, 'name' => $template->name,
            ]))
            ->assertRedirect();
        $this->assertSame([], $this->sections()->categoryIdsFor($template->fresh()), 'submitting the selector empty clears the links');
    }

    public function test_a_new_version_keeps_the_sections_and_publishing_it_moves_the_links(): void
    {
        $uae = $this->scenario->country;
        $category = ClassifiedCategory::factory()->create();
        $v1 = $this->create(['country_id' => $uae->id, 'classified_category_ids' => [$category->id]]);
        $this->publish($v1);

        $this->actingAs($this->admin, 'admin')
            ->put(route('admin.contracts.templates.update', $v1), $this->form([
                'content_en' => '<p>New text</p>', 'content_ar' => '<p>نص جديد</p>', 'name' => $v1->name, 'classified_category_ids' => [$category->id],
            ]))
            ->assertRedirect();

        $v2 = ClassifiedContractTemplate::where('version', 2)->firstOrFail();
        $this->assertSame([$category->id], $this->sections()->categoryIdsFor($v2), 'the draft shows the lineage sections');
        $this->assertSame($v1->id, ContractCountryAssignment::sole()->contract_template_id, 'sellers keep v1 until v2 is published');

        $this->publish($v2);

        $this->assertSame($v2->id, ContractCountryAssignment::sole()->contract_template_id);
        $this->assertSame($v2->id, $this->resolver()->resolveForCategory('classified', $category->fresh(), $uae)->template->id);
    }

    public function test_the_category_form_and_the_template_form_share_the_same_links(): void
    {
        $uae = $this->scenario->country;
        $category = ClassifiedCategory::factory()->create();
        $template = $this->contractTemplate('classified', 'Cars terms', $uae);

        $this->actingAs($this->admin, 'admin')
            ->putJson(route('admin.classifieds.categories.update', $category), [
                'name_en' => $category->name_en, 'name_ar' => $category->name_ar, 'is_active' => 1, 'sort_order' => 0,
                'requires_contract' => 1, 'country_contracts' => [$uae->id => $template->id],
            ])
            ->assertOk();

        $this->assertSame([$category->id], $this->sections()->categoryIdsFor($template));
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.contracts.templates.edit', $template))
            ->assertOk()
            ->assertSee($category->name_ar);
    }

    public function test_the_category_list_shows_the_per_country_contracts_and_labels_the_legacy_one(): void
    {
        $uae = $this->scenario->country;
        $category = ClassifiedCategory::factory()->create(['requires_contract' => true]);
        $countryTemplate = $this->contractTemplate('classified', 'UAE Real Estate Terms', $uae);
        $legacyDraft = $this->contractTemplate('classified', 'Old Draft Terms', null, 1, false);
        $category->update(['contract_template_id' => $legacyDraft->id]);
        ContractCountryAssignment::create([
            'classified_category_id' => $category->id, 'country_id' => $uae->id, 'contract_template_id' => $countryTemplate->id,
        ]);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.classifieds.categories.index'))
            ->assertOk()
            ->assertSeeInOrder(['UAE Real Estate Terms', __('contracts.admin.legacy_country'), 'Old Draft Terms', __('contracts.admin.list_not_in_force')]);
    }

    public function test_deleting_a_draft_removes_its_links_but_a_published_contract_stays(): void
    {
        $uae = $this->scenario->country;
        $category = ClassifiedCategory::factory()->create();
        $draft = $this->create(['country_id' => $uae->id, 'classified_category_ids' => [$category->id]]);

        $this->actingAs($this->admin, 'admin')->delete(route('admin.contracts.templates.destroy', $draft))->assertRedirect();
        $this->assertSame(0, ClassifiedContractTemplate::count());
        $this->assertSame(0, ContractCountryAssignment::count());

        $published = $this->create(['name' => 'Kept terms', 'country_id' => $uae->id, 'classified_category_ids' => [$category->id]]);
        $this->publish($published);
        $this->actingAs($this->admin, 'admin')->delete(route('admin.contracts.templates.destroy', $published))->assertRedirect();
        $this->assertSame(1, ClassifiedContractTemplate::count());
    }

    // ── Admin list: filter + warning ─────────────────────────────────────────

    public function test_templates_can_be_filtered_by_section_with_parent_inheritance_and_uncovered_sections_are_listed(): void
    {
        $uae = $this->scenario->country;
        $parent = ClassifiedCategory::factory()->create(['name_en' => 'Property']);
        $child = ClassifiedCategory::factory()->childOf($parent)->create(['name_en' => 'Apartments']);
        $vehicles = ClassifiedCategory::factory()->create(['name_en' => 'Vehicles']);
        $services = ClassifiedCategory::factory()->create(['name_en' => 'Services']);

        $propertyTemplate = $this->create(['name' => 'Property terms', 'country_id' => $uae->id, 'classified_category_ids' => [$parent->id]]);
        $this->publish($propertyTemplate);
        $vehicleTemplate = $this->create(['name' => 'Vehicle terms', 'country_id' => $uae->id, 'classified_category_ids' => [$vehicles->id]]);
        $this->publish($vehicleTemplate);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.contracts.templates.index', ['section' => $child->id]))
            ->assertOk()
            ->assertSee('Property terms')
            ->assertDontSee('Vehicle terms');

        $this->assertSame(['Services'], $response->viewData('uncoveredSections')->pluck('name_en')->all());

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.contracts.templates.index'))
            ->assertOk()
            ->assertSee('Property terms')
            ->assertSee('Vehicle terms');
    }

    // ── Listing creation uses the resolver ───────────────────────────────────

    public function test_a_classified_listing_in_a_sub_section_needs_the_parent_contract(): void
    {
        Bus::fake();
        $uae = $this->scenario->country;
        $parent = ClassifiedCategory::factory()->create();
        $child = ClassifiedCategory::factory()->childOf($parent)->create();
        $template = $this->create(['country_id' => $uae->id, 'classified_category_ids' => [$parent->id]]);
        $this->publish($template);

        $vendor = $this->scenario->vendor;
        $payload = [
            'classified_category_id' => $child->id, 'country_id' => $uae->id, 'listing_purpose' => 'sale',
            'title_en' => 'Flat', 'title_ar' => 'شقة', 'price' => 100, 'currency' => 'AED',
        ];

        $this->assertSame($template->id, app(CategoryContractService::class)->unsignedTemplateFor($vendor, 'classified', $child->id)?->id);

        $pending = app(ClassifiedListingService::class)->create($vendor, $payload);
        $this->assertSame(ClassifiedListingStatus::PendingContract, $pending->status);

        $accepted = app(ClassifiedListingService::class)->create($vendor, $payload + ['contract_accepted' => true]);
        $this->assertSame(ClassifiedListingStatus::PendingReview, $accepted->status);
        $this->assertSame($template->id, $accepted->contract_template_id);
    }

    public function test_a_listing_in_a_section_without_a_contract_goes_straight_to_review(): void
    {
        Bus::fake();
        $category = ClassifiedCategory::factory()->create();

        $listing = app(ClassifiedListingService::class)->create($this->scenario->vendor, [
            'classified_category_id' => $category->id, 'country_id' => $this->scenario->country->id, 'listing_purpose' => 'sale',
            'title_en' => 'Car', 'title_ar' => 'سيارة', 'price' => 100, 'currency' => 'AED',
        ]);

        $this->assertSame(ClassifiedListingStatus::PendingReview, $listing->status);
        $this->assertNull($listing->contract_template_id);
    }

    public function test_a_classified_vendor_publishing_in_a_real_estate_sub_section_is_asked_to_sign_the_inherited_contract(): void
    {
        Bus::fake();
        $publicRoot = sys_get_temp_dir().'/classified-contract-test-'.uniqid();
        config(['filesystems.disks.public.root' => $publicRoot]);
        Storage::forgetDisk('public');
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($publicRoot));
        $uae = $this->scenario->country;
        $vendor = $this->scenario->vendor;
        $vendor->update(['vendor_type' => VendorType::ClassifiedVendor]);
        $owner = VendorAdmin::create([
            'vendor_id' => $vendor->id, 'name' => 'Souq Owner', 'email' => 'souq-owner@example.test',
            'password' => bcrypt('password'), 'role' => 'owner', 'is_owner' => true, 'is_active' => true,
        ]);

        $realEstate = ClassifiedCategory::factory()->create(['slug' => 'real-estate']);
        $apartments = ClassifiedCategory::factory()->childOf($realEstate)->create(['slug' => 'apartments']);
        $template = $this->create(['name' => 'Real estate terms', 'country_id' => $uae->id, 'classified_category_ids' => [$realEstate->id]]);
        $this->publish($template);

        $child = collect($this->actingAs($owner, 'vendor')->getJson(route('partner.classifieds.categories'))->assertOk()->json('data'))
            ->firstWhere('id', $realEstate->id)['children'][0];
        $this->assertSame($apartments->id, $child['id']);
        $this->assertTrue($child['contract_pending']);
        $this->assertNotEmpty($child['contract_template_content']);

        $payload = [
            'classified_category_id' => $apartments->id, 'country_id' => $uae->id, 'listing_purpose' => 'rent',
            'title_en' => 'Flat', 'title_ar' => 'شقة', 'price' => 100, 'currency' => 'AED',
            'latitude' => 25.08, 'longitude' => 55.14, 'images' => [UploadedFile::fake()->image('flat.jpg')],
        ];

        $this->actingAs($owner, 'vendor')->post(route('partner.classifieds.store'), $payload, ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['signature_name', 'agreed']);

        $this->actingAs($owner, 'vendor')->post(route('partner.classifieds.store'), $payload + [
            'images' => [UploadedFile::fake()->image('flat.jpg')], 'signature_name' => 'Souq Owner', 'language' => 'ar', 'agreed' => '1',
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->assertTrue(VendorContract::where('vendor_id', $vendor->id)->where('contract_template_id', $template->id)->exists());
        $this->assertNull(app(CategoryContractService::class)->unsignedTemplateFor($vendor->fresh(), 'classified', $apartments->id));
    }

    // ── Seeder ───────────────────────────────────────────────────────────────

    public function test_the_section_seeder_is_idempotent_and_only_links_existing_unlinked_categories(): void
    {
        Admin::factory()->create();
        $realEstate = ClassifiedCategory::factory()->create(['slug' => 'real-estate']);
        $optedOut = ClassifiedCategory::factory()->create(['slug' => 'vehicles', 'requires_contract' => false]);
        $custom = $this->contractTemplate('classified', 'Hand written travel terms');
        $travel = ClassifiedCategory::factory()->create(['slug' => 'travel', 'contract_template_id' => $custom->id]);

        $this->seed(ContractTemplateSeeder::class);
        $count = ClassifiedContractTemplate::count();
        $this->assertSame(7, ClassifiedContractTemplate::where('category_scope', '!=', 'customer')->count(), '2 generic + 4 sections + the custom template');
        $this->assertSame(2, ClassifiedContractTemplate::where('category_scope', 'customer')->count(), 'the sample customer purchase contracts (real estate, vehicles)');

        $edited = ClassifiedContractTemplate::where('name', 'Real Estate Advertiser Agreement')->firstOrFail();
        $edited->update(['content_en' => 'Edited by the admin']);

        $this->seed(ContractTemplateSeeder::class);

        $this->assertSame($count, ClassifiedContractTemplate::count());
        $this->assertSame('Edited by the admin', $edited->fresh()->content_en, 'an edited template is never overwritten');
        $this->assertSame($edited->id, $realEstate->fresh()->contract_template_id);
        $this->assertTrue($realEstate->fresh()->requires_contract);
        $this->assertNull($optedOut->fresh()->contract_template_id, 'a section an admin switched off is left alone');
        $this->assertSame($custom->id, $travel->fresh()->contract_template_id, 'an existing link is never replaced');
    }
}
