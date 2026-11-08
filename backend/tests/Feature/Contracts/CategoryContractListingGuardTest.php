<?php

namespace Tests\Feature\Contracts;

use App\Models\Admin;
use App\Models\Category;
use App\Models\ClassifiedContractTemplate;
use App\Models\Country;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Vendor;
use App\Models\VendorAdmin;
use App\Models\VendorListing;
use App\Services\Vendor\CategoryContractService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;
use Tests\Support\CountryContractFixtures;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * QA "اخر المشاكل" #1: a product category that requires an individual contract must stop the partner listing wizard
 * until the seller signs that category's contract, also when the seller already signed the same template for another
 * category and when the listing is a further variant (size) of the same product.
 */
class CategoryContractListingGuardTest extends TestCase
{
    use CountryContractFixtures;
    use RefreshDatabase;

    private MarketplaceScenario $scenario;

    private Country $kuwait;

    private Vendor $vendor;

    private VendorAdmin $owner;

    private ClassifiedContractTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->scenario = MarketplaceScenario::make()->build();

        $this->kuwait = $this->extraCountry('KW', 'Kuwait');
        $this->vendor = $this->vendorIn($this->kuwait, 'Maktoom');
        Permission::firstOrCreate(['name' => 'listings.create', 'guard_name' => 'vendor']);
        $this->owner = $this->ownerFor($this->vendor);
        $this->owner->givePermissionTo('listings.create');

        $this->template = $this->contractTemplate('product', 'Product Category Contract Kuwait', $this->kuwait);
    }

    private function rootCategory(string $name): Category
    {
        return Category::create([
            'id' => (string) Str::uuid(), 'name_en' => $name, 'name_ar' => $name, 'slug' => Str::slug($name).'-'.Str::random(6),
            'commission_rate' => 5, 'is_active' => true, 'is_visible' => true,
        ]);
    }

    /**
     * @return array{0: Product, 1: ProductVariant, 2: ProductVariant}
     */
    private function productWithSizes(Category $category): array
    {
        $product = $this->productIn($category);
        $product->forceFill(['has_variants' => true])->save();
        $xs = $product->variants()->create(['sku' => 'XS-'.uniqid(), 'variant_name' => 'XS', 'is_default' => true, 'is_active' => true, 'position' => 0]);
        $s = $product->variants()->create(['sku' => 'S-'.uniqid(), 'variant_name' => 'S', 'is_default' => false, 'is_active' => true, 'position' => 1]);

        return [$product, $xs, $s];
    }

    private function signFor(Category $category): void
    {
        app(CategoryContractService::class)->sign($this->vendor, $this->owner, 'product', $category->id, 'en', 'Owner', '127.0.0.1', 'phpunit');
    }

    private function storeVariant(ProductVariant $variant): TestResponse
    {
        return $this->actingAs($this->owner, 'vendor')
            ->postJson(route('partner.listings.store'), ['product_variant_id' => $variant->id, 'country_id' => $this->kuwait->id]);
    }

    public function test_sub_category_inherits_the_parents_requirement_and_blocks_both_sizes_until_signed(): void
    {
        $abaya = $this->rootCategory('Abaya');
        $abaya->forceFill(['requires_contract' => true])->save();
        $this->assignCategoryTemplate($abaya, $this->kuwait, $this->template);
        $womens = $this->childCategory($abaya, "Women's Abayas");
        [$product, $xs, $s] = $this->productWithSizes($womens);

        $this->actingAs($this->owner, 'vendor')
            ->getJson(route('partner.listings.contract-check', ['product_id' => $product->id]))
            ->assertOk()
            ->assertJsonPath('requires_contract', true)
            ->assertJsonPath('signed', false)
            ->assertJsonPath('contract.template_id', $this->template->id)
            ->assertJsonPath('contract.category_id', $abaya->id)
            ->assertJsonPath('contract.accept_url', route('partner.contracts.accept', $this->template));

        $this->storeVariant($xs)->assertStatus(422)->assertJsonPath('code', 'contract_required')
            ->assertJsonPath('contract.category_id', $abaya->id);
        $this->storeVariant($s)->assertStatus(422)->assertJsonPath('code', 'contract_required');
        $this->assertSame(0, VendorListing::where('vendor_id', $this->vendor->id)->count());

        // Signing from the panel shown on product selection covers both sizes; the wizard then lets the listing through.
        $this->actingAs($this->owner, 'vendor')
            ->postJson(route('partner.contracts.accept', $this->template), [
                'signature_name' => 'Maktoom', 'language' => 'ar', 'agreed' => 1,
                'category_scope' => 'product', 'category_id' => $abaya->id,
            ])
            ->assertOk();

        $this->actingAs($this->owner, 'vendor')
            ->getJson(route('partner.listings.contract-check', ['product_id' => $product->id]))
            ->assertJsonPath('signed', true)
            ->assertJsonPath('contract', null);
        $this->storeVariant($s)->assertJsonMissing(['code' => 'contract_required']);
    }

    public function test_a_signature_for_another_category_with_the_same_template_does_not_cover_this_one(): void
    {
        $kitchen = $this->rootCategory('Kitchen & Dining');
        $kitchen->forceFill(['requires_contract' => true])->save();
        $this->assignCategoryTemplate($kitchen, $this->kuwait, $this->template);
        $this->signFor($kitchen);

        $abaya = $this->rootCategory('Abaya');
        $abaya->forceFill(['requires_contract' => true])->save();
        $this->assignCategoryTemplate($abaya, $this->kuwait, $this->template);
        $womens = $this->childCategory($abaya, "Women's Abayas");
        $womens->forceFill(['requires_contract' => true])->save();
        $this->assignCategoryTemplate($womens, $this->kuwait, $this->template);
        [, $xs] = $this->productWithSizes($womens);

        $this->storeVariant($xs)->assertStatus(422)->assertJsonPath('code', 'contract_required')
            ->assertJsonPath('contract.category_id', $womens->id);
    }

    public function test_the_country_default_contract_applies_when_the_category_has_no_assignment_of_its_own(): void
    {
        $default = $this->contractTemplate('product', 'Kuwait default category contract', $this->kuwait, default: true);
        $abaya = $this->rootCategory('Abaya');
        $abaya->forceFill(['requires_contract' => true])->save();
        $womens = $this->childCategory($abaya, "Women's Abayas");
        [, $xs] = $this->productWithSizes($womens);

        $this->storeVariant($xs)->assertStatus(422)->assertJsonPath('code', 'contract_required')
            ->assertJsonPath('contract.template_id', $default->id);
    }

    public function test_the_admin_category_form_switch_and_kuwait_assignment_are_enforced_in_the_wizard(): void
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');
        $abaya = $this->rootCategory('Abaya');
        $womens = $this->childCategory($abaya, "Women's Abayas");

        foreach ([$abaya, $womens] as $category) {
            $this->withoutMiddleware()
                ->putJson(route('admin.categories.update', $category->id), [
                    'name_en' => $category->name_en, 'name_ar' => $category->name_ar, 'slug' => strtolower($category->slug),
                    'parent_id' => $category->parent_id, 'is_active' => 1, 'is_visible' => 1,
                    'requires_contract' => '1', 'contract_template_id' => '',
                    'country_contracts' => [$this->kuwait->id => $this->template->id],
                ])
                ->assertOk();
        }

        $this->assertTrue($womens->fresh()->requiresIndividualContract());
        [, $xs] = $this->productWithSizes($womens->fresh());

        $this->storeVariant($xs)->assertStatus(422)->assertJsonPath('code', 'contract_required')
            ->assertJsonPath('contract.category_id', $womens->id);
    }

    public function test_the_explain_command_reports_the_unsigned_category_contract(): void
    {
        $abaya = $this->rootCategory('Abaya');
        $abaya->forceFill(['requires_contract' => true])->save();
        $this->assignCategoryTemplate($abaya, $this->kuwait, $this->template);
        $womens = $this->childCategory($abaya, "Women's Abayas");
        [, $xs] = $this->productWithSizes($womens);

        $this->artisan('contracts:explain-listing', ['vendor' => $this->vendor->id, 'product' => $xs->sku])
            ->expectsOutputToContain('Product Category Contract Kuwait v1')
            ->expectsOutputToContain('NOT SIGNED')
            ->assertSuccessful();
    }
}
