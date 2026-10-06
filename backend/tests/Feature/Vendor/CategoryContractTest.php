<?php

namespace Tests\Feature\Vendor;

use App\Models\Admin;
use App\Models\ClassifiedCategory;
use App\Models\ClassifiedContractTemplate;
use App\Models\ClassifiedListing;
use App\Models\Vendor;
use App\Models\VendorAdmin;
use App\Models\VendorCategoryEnrollment;
use App\Models\VendorContract;
use App\Services\Vendor\CategoryContractService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

class CategoryContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);

        foreach (['vendors.assigned_only', 'classifieds.view', 'vendors.view'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'admin']);
        }
    }

    private function activeScenario(): MarketplaceScenario
    {
        $scenario = MarketplaceScenario::make()->build();
        $scenario->vendor->update(['global_status' => 'active', 'onboarding_completed_at' => now()]);

        return $scenario;
    }

    private function template(string $scope, string $name, int $version = 1, bool $published = true, ?Admin $admin = null): ClassifiedContractTemplate
    {
        return ClassifiedContractTemplate::create([
            'name' => $name,
            'category_scope' => $scope,
            'version' => $version,
            'content_en' => 'EN {{vendor.store_name}} signed by {{vendor.signer_name}} v{{contract.version}}',
            'content_ar' => 'AR {{vendor.store_name}} وقّعه {{vendor.signer_name}} v{{contract.version}}',
            'is_active' => true,
            'is_published' => $published,
            'variables_schema' => ClassifiedContractTemplate::variableKeys(),
            'created_by_admin_id' => ($admin ?? Admin::factory()->create())->id,
        ]);
    }

    private function classifiedCategory(?ClassifiedContractTemplate $template): ClassifiedCategory
    {
        return ClassifiedCategory::create([
            'name_en' => 'Cars', 'name_ar' => 'سيارات', 'slug' => 'cars-'.Str::random(6),
            'is_active' => true, 'contract_template_id' => $template?->id,
        ]);
    }

    private function classifiedListing(MarketplaceScenario $scenario, ClassifiedCategory $category): void
    {
        $listing = new ClassifiedListing;
        $listing->forceFill([
            'listing_number' => 'L'.Str::random(8), 'slug' => Str::random(10),
            'seller_type' => Vendor::class, 'seller_id' => $scenario->vendor->id,
            'classified_category_id' => $category->id, 'country_id' => $scenario->country->id,
            'listing_purpose' => 'sale', 'title_en' => 't', 'title_ar' => 't',
            'price' => 100, 'currency' => 'AED', 'status' => 'active',
        ])->save();
    }

    private function vendorAdmin(MarketplaceScenario $scenario): VendorAdmin
    {
        return VendorAdmin::create([
            'vendor_id' => $scenario->vendor->id, 'name' => 'Vendor Owner',
            'email' => 'owner-'.Str::random(6).'@example.test', 'password' => bcrypt('password'),
            'role' => 'owner', 'is_owner' => true, 'is_active' => true,
        ]);
    }

    private function service(): CategoryContractService
    {
        return app(CategoryContractService::class);
    }

    public function test_signature_freezes_rendered_text_and_clears_the_pending_contract(): void
    {
        $scenario = $this->activeScenario();
        $template = $this->template('classified', 'Classified terms');
        $category = $this->classifiedCategory($template);
        $this->classifiedListing($scenario, $category);

        $this->assertCount(1, $this->service()->pendingForVendor($scenario->vendor));

        $contract = $this->service()->sign(
            $scenario->vendor, null, 'classified', $category->id, 'ar', 'Jane Owner', '127.0.0.1', 'phpunit',
        );

        $this->assertSame('ar', $contract->language_signed);
        $this->assertStringContainsString($scenario->vendor->store_name, $contract->rendered_content);
        $this->assertStringContainsString('Jane Owner', $contract->rendered_content);
        $this->assertSame(64, strlen($contract->rendered_content_hash));
        $this->assertCount(0, $this->service()->pendingForVendor($scenario->vendor));

        $enrollment = VendorCategoryEnrollment::where('vendor_id', $scenario->vendor->id)->sole();
        $this->assertSame(VendorCategoryEnrollment::STATUS_SIGNED, $enrollment->status);
        $this->assertSame($contract->id, $enrollment->active_contract_id);
    }

    public function test_unpublished_draft_is_not_enforced_until_published(): void
    {
        $scenario = $this->activeScenario();
        $draft = $this->template('classified', 'Draft terms', 1, false);
        $category = $this->classifiedCategory($draft);
        $this->classifiedListing($scenario, $category);

        $this->assertCount(0, $this->service()->pendingForVendor($scenario->vendor));
    }

    public function test_publishing_a_new_version_moves_categories_and_asks_signed_vendors_to_resign(): void
    {
        $scenario = $this->activeScenario();
        $admin = Admin::factory()->create();
        $v1 = $this->template('classified', 'Classified terms', 1, true, $admin);
        $category = $this->classifiedCategory($v1);
        $this->classifiedListing($scenario, $category);
        $this->service()->sign($scenario->vendor, null, 'classified', $category->id, 'en', 'Jane', '127.0.0.1', 'phpunit');

        $v2 = $this->template('classified', 'Classified terms', 2, false, $admin);
        $this->assertCount(0, $this->service()->pendingForVendor($scenario->vendor), 'draft must not affect vendors yet');

        $vendorsToResign = $this->service()->publish($v2);

        $this->assertTrue($vendorsToResign->contains(fn (Vendor $vendor) => $vendor->is($scenario->vendor)));
        $this->assertFalse($v1->fresh()->is_published);
        $this->assertTrue($v2->fresh()->is_published);
        $this->assertSame($v2->id, $category->fresh()->contract_template_id);

        $pending = $this->service()->pendingForVendor($scenario->vendor);
        $this->assertCount(1, $pending);
        $this->assertSame($v2->id, $pending->first()['template']->id);
        $this->assertSame(
            VendorCategoryEnrollment::STATUS_RE_SIGN,
            VendorCategoryEnrollment::where('vendor_id', $scenario->vendor->id)->sole()->status,
        );
    }

    public function test_product_category_gates_product_listing_creation_until_signed(): void
    {
        $scenario = $this->activeScenario();
        $template = $this->template('product', 'Product terms');
        $scenario->category->update(['contract_template_id' => $template->id]);
        $scenario->vendor->update(['vendor_type' => 'product_vendor']);
        $admin = $this->vendorAdmin($scenario);
        Permission::firstOrCreate(['name' => 'listings.create', 'guard_name' => 'vendor']);
        $admin->givePermissionTo('listings.create');

        $this->assertCount(1, $this->service()->pendingForVendor($scenario->vendor));

        $this->actingAs($admin, 'vendor')
            ->postJson(route('partner.listings.store'), ['product_id' => $scenario->product->id])
            ->assertStatus(422)
            ->assertJsonPath('redirect', route('partner.contracts.pending'))
            ->assertJsonPath('contract.template_id', $template->id)
            ->assertJsonPath('contract.category_scope', 'product')
            ->assertJsonPath('contract.category_id', $scenario->category->id)
            ->assertJsonPath('contract.accept_url', route('partner.contracts.accept', $template));

        $this->service()->sign($scenario->vendor, $admin, 'product', $scenario->category->id, 'en', 'Owner', '127.0.0.1', 'phpunit');

        $this->assertNull($this->service()->unsignedTemplateFor($scenario->vendor, 'product', $scenario->category->id));
    }

    public function test_inline_signing_from_listing_form_signs_the_named_category(): void
    {
        $scenario = $this->activeScenario();
        $scenario->vendor->update(['vendor_type' => 'product_vendor']);
        $template = $this->template('product', 'Product terms');
        $scenario->category->update(['contract_template_id' => $template->id]);
        $admin = $this->vendorAdmin($scenario);

        $this->actingAs($admin, 'vendor')
            ->postJson(route('partner.contracts.accept', $template), [
                'signature_name' => 'Owner',
                'language' => 'ar',
                'agreed' => 1,
                'category_scope' => 'product',
                'category_id' => $scenario->category->id,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $contract = VendorContract::where('vendor_id', $scenario->vendor->id)->sole();
        $this->assertSame('ar', $contract->language_signed);
        $this->assertSame($scenario->category->id, $contract->product_category_id);
        $this->assertNull($this->service()->unsignedTemplateFor($scenario->vendor, 'product', $scenario->category->id));
    }

    public function test_vendor_is_redirected_to_pending_contracts_then_returns_after_signing(): void
    {
        $scenario = $this->activeScenario();
        $scenario->vendor->update(['vendor_type' => 'classified_vendor']);
        $template = $this->template('classified', 'Cars terms');
        $category = $this->classifiedCategory($template);
        $this->classifiedListing($scenario, $category);
        $admin = $this->vendorAdmin($scenario);

        $this->actingAs($admin, 'vendor')
            ->get(route('partner.classifieds.index'))
            ->assertRedirect(route('partner.contracts.pending'));

        $this->actingAs($admin, 'vendor')
            ->get(route('partner.contracts.pending'))
            ->assertOk()
            ->assertSee('Cars terms');

        $this->actingAs($admin, 'vendor')
            ->post(route('partner.contracts.accept', $template), ['signature_name' => 'Owner', 'language' => 'en', 'agreed' => '1'])
            ->assertRedirect(route('partner.classifieds.index'));

        $this->assertSame(1, VendorContract::where('vendor_id', $scenario->vendor->id)->count());

        $this->actingAs($admin, 'vendor')
            ->get(route('partner.contracts.history'))
            ->assertOk()
            ->assertSee('Owner');
    }

    public function test_admin_contract_pages_render_for_both_scopes_and_publish_works(): void
    {
        $scenario = $this->activeScenario();
        $admin = Admin::factory()->create();
        $admin->givePermissionTo(['classifieds.view', 'vendors.view', 'vendors.assigned_only']);

        $classifiedDraft = $this->template('classified', 'Classified terms', 1, false, $admin);
        $productTemplate = $this->template('product', 'Product terms', 1, true, $admin);
        $scenario->category->update(['contract_template_id' => $productTemplate->id]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.contracts.templates.index'))
            ->assertOk()
            ->assertSee('Classified terms')
            ->assertSee('Product terms');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.contracts.templates.index', ['scope' => 'product']))
            ->assertOk()
            ->assertSee('Product terms')
            ->assertDontSee('Classified terms');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.contracts.templates.signatures', $productTemplate))
            ->assertOk()
            ->assertSee($scenario->vendor->store_name)
            ->assertViewHas('summary', fn (array $summary) => $summary['pending'] === 1);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.contracts.signatures.index'))
            ->assertOk();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.contracts.templates.publish', $classifiedDraft))
            ->assertRedirect();

        $this->assertTrue($classifiedDraft->fresh()->is_published);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.vendors.contracts.index', $scenario->vendor))
            ->assertOk();
    }
    public function test_preview_fills_every_variable_and_history_opens_the_signed_text(): void
    {
        $scenario = $this->activeScenario();
        $scenario->vendor->update(['vendor_type' => 'product_vendor']);
        $template = $this->template('product', 'Product terms');
        $scenario->category->update(['contract_template_id' => $template->id]);
        $admin = $this->vendorAdmin($scenario);

        $this->actingAs($admin, 'vendor')
            ->get(route('partner.contracts.preview', $template))
            ->assertOk()
            ->assertSee('EN '.$scenario->vendor->store_name)
            ->assertDontSee('{{vendor.store_name}}')
            ->assertDontSee('{{vendor.signer_name}}');

        $contract = $this->service()->sign($scenario->vendor, $admin, 'product', $scenario->category->id, 'en', 'Owner Name', '127.0.0.1', 'phpunit');

        $this->actingAs($admin, 'vendor')
            ->get(route('partner.contracts.signed', $contract))
            ->assertOk()
            ->assertSee('EN '.$scenario->vendor->store_name.' signed by Owner Name');

        $otherVendor = Vendor::factory()->create(['global_status' => 'active', 'onboarding_completed_at' => now()]);
        $otherAdmin = VendorAdmin::create([
            'vendor_id' => $otherVendor->id, 'name' => 'Other', 'email' => 'other-'.Str::random(6).'@example.test',
            'password' => bcrypt('password'), 'role' => 'owner', 'is_owner' => true, 'is_active' => true,
        ]);
        $this->actingAs($otherAdmin, 'vendor')
            ->get(route('partner.contracts.signed', $contract))
            ->assertNotFound();
    }
    public function test_admin_edit_page_uses_the_rich_editor_and_vendor_values_are_escaped_in_contracts(): void
    {
        $scenario = $this->activeScenario();
        $scenario->vendor->update(['store_name' => '<img src=x onerror=alert(1)>']);
        $admin = Admin::factory()->create();
        $admin->givePermissionTo(['classifieds.view', 'vendors.view', 'vendors.assigned_only']);
        $template = ClassifiedContractTemplate::create([
            'name' => 'Editor terms', 'category_scope' => 'product', 'version' => 1,
            'content_en' => "<p>Store: {{vendor.store_name}}</p>", 'content_ar' => '<p>AR</p>',
            'is_active' => true, 'is_published' => true, 'created_by_admin_id' => $admin->id,
        ]);
        $scenario->category->update(['contract_template_id' => $template->id]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.contracts.templates.edit', $template))
            ->assertOk()
            ->assertSee('data-rich-editor', false);

        $contract = $this->service()->sign($scenario->vendor, null, 'product', $scenario->category->id, 'en', 'Owner', '127.0.0.1', 'phpunit');

        $this->assertStringNotContainsString('<img', $contract->rendered_content);
        $this->assertStringContainsString('&lt;img', $contract->rendered_content);
    }
}
