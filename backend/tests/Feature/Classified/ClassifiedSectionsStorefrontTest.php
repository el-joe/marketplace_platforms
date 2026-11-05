<?php

namespace Tests\Feature\Classified;

use App\Models\Admin;
use App\Models\ClassifiedCategory;
use App\Models\ClassifiedListing;
use App\Models\Country;
use App\Models\Customer;
use App\Models\File;
use App\Models\VendorContract;
use App\Services\Customer\ClassifiedSectionService;
use App\Services\Shared\ClassifiedListingService;
use App\Services\Vendor\CategoryContractService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\Support\CountryContractFixtures;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * Requirement 8 (storefront API): sections are rendered from configuration (placements, countries, order, image),
 * and a disabled category is hidden everywhere without deleting anything.
 */
class ClassifiedSectionsStorefrontTest extends TestCase
{
    use CountryContractFixtures;
    use RefreshDatabase;

    private MarketplaceScenario $scenario;

    private Country $home;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->scenario = MarketplaceScenario::make()->build();
        $this->home = $this->scenario->country;
        $this->home->update(['site_code' => 'ae-'.Str::lower(Str::random(5))]);
    }

    private function url(string $path, ?Country $country = null, array $query = []): string
    {
        $qs = $query ? '?'.http_build_query($query) : '';

        return '/api/customer/v1/'.($country ?? $this->home)->site_code."/{$path}{$qs}";
    }

    private function listingIn(ClassifiedCategory $category, string $title = 'Ad', ?Country $country = null): ClassifiedListing
    {
        $listing = ClassifiedListing::create([
            'listing_number' => 'CL-'.Str::upper(Str::random(8)), 'seller_type' => Customer::class,
            'seller_id' => $this->scenario->customer->id, 'classified_category_id' => $category->id,
            'country_id' => ($country ?? $this->home)->id, 'listing_purpose' => 'sale', 'title_en' => $title, 'title_ar' => $title,
            'price' => 100, 'currency' => 'AED', 'price_negotiable' => false, 'status' => 'active',
            'latitude' => 25.2, 'longitude' => 55.3,
        ]);
        $listing->forceFill(['slug' => Str::slug($title).'-'.Str::lower(Str::random(4))])->save();

        return $listing;
    }

    /**
     * @return list<string>
     */
    private function slugs(array $nodes): array
    {
        return array_column(array_filter($nodes, fn ($n) => $n['type'] === 'classified'), 'slug');
    }

    // ── Navigation / sections tree ───────────────────────────────────────────

    public function test_category_tree_lists_only_active_sections_placed_for_the_country_in_the_admins_order(): void
    {
        $other = $this->extraCountry('OM', 'Oman');
        ClassifiedCategory::factory()->create(['slug' => 'vehicles', 'sort_order' => 2]);
        $realEstate = ClassifiedCategory::factory()->placedOn(['nav', 'landing'])->create(['slug' => 'real-estate', 'sort_order' => 1]);
        ClassifiedCategory::factory()->childOf($realEstate)->create(['slug' => 'apartments']);
        ClassifiedCategory::factory()->childOf($realEstate)->inactive()->create(['slug' => 'villas']);
        ClassifiedCategory::factory()->inactive()->create(['slug' => 'disabled-root']);
        ClassifiedCategory::factory()->placedOn(['home'])->create(['slug' => 'home-only']);
        $omanOnly = ClassifiedCategory::factory()->create(['slug' => 'oman-only']);
        $omanOnly->countries()->sync([$other->id]);
        ClassifiedCategory::flushStorefrontCaches();

        $tree = $this->getJson($this->url('categories'))->assertOk()->json('data');

        $this->assertSame(['real-estate', 'vehicles'], $this->slugs($tree));
        $node = collect($tree)->firstWhere('slug', 'real-estate');
        $this->assertSame(['apartments'], array_column($node['children'], 'slug'));
        $this->assertSame(['nav', 'landing'], $node['placements']);

        $this->assertContains('oman-only', $this->slugs($this->getJson($this->url('categories', $other))->assertOk()->json('data')));
    }

    public function test_unified_nav_endpoint_applies_the_same_visibility(): void
    {
        ClassifiedCategory::factory()->create(['slug' => 'shown']);
        ClassifiedCategory::factory()->inactive()->create(['slug' => 'hidden']);
        ClassifiedCategory::flushStorefrontCaches();

        $classifieds = collect($this->getJson($this->url('nav'))->assertOk()->json('data.nav'))->firstWhere('section', 'classifieds');

        $this->assertSame(['shown'], array_column($classifieds['nodes'], 'slug'));
    }

    public function test_sections_endpoint_returns_configured_placements_with_their_own_ads_only(): void
    {
        $other = $this->extraCountry('OM', 'Oman');
        $property = ClassifiedCategory::factory()->placedOn(['home'])->create(['slug' => 'property', 'sort_order' => 2, 'description_en' => 'Homes']);
        $apartments = ClassifiedCategory::factory()->childOf($property)->create(['slug' => 'flats']);
        $cars = ClassifiedCategory::factory()->placedOn(['home'])->create(['slug' => 'cars', 'sort_order' => 1]);
        $navOnly = ClassifiedCategory::factory()->placedOn(['nav'])->create(['slug' => 'nav-only']);
        $disabled = ClassifiedCategory::factory()->placedOn(['home'])->inactive()->create(['slug' => 'disabled']);
        File::create([
            'key' => 'k', 'path' => 'classified-categories/x.jpg', 'storage_type' => 'public', 'file_type' => 'classified_category_image',
            'model_type' => ClassifiedCategory::class, 'model_id' => $cars->id, 'is_primary' => 1, 'position' => 0,
        ]);
        $this->listingIn($property, 'House');
        $this->listingIn($apartments, 'Flat');
        $this->listingIn($cars, 'Sedan');
        $this->listingIn($cars, 'Sedan in Oman', $other);
        $this->listingIn($navOnly, 'Nav ad');
        $this->listingIn($disabled, 'Hidden ad');
        ClassifiedCategory::flushStorefrontCaches();

        $sections = $this->getJson($this->url('classified/sections', null, ['placement' => 'home']))->assertOk()->json('data');

        $this->assertSame(['cars', 'property'], array_column($sections, 'slug'), 'ordered by the admin, only placed + active');
        $byslug = collect($sections)->keyBy('slug');
        $this->assertSame(['Sedan'], array_column($byslug['cars']['listings'], 'title_en'), 'only this section and this country');
        $this->assertNotNull($byslug['cars']['image_url']);
        $this->assertEqualsCanonicalizing(['House', 'Flat'], array_column($byslug['property']['listings'], 'title_en'), 'sub-category ads belong to the section');
        $this->assertSame(2, $byslug['property']['total']);
        $this->assertSame(['flats'], array_column($byslug['property']['children'], 'slug'));
        $this->assertSame('Homes', $byslug['property']['description']['en']);

        $this->getJson($this->url('classified/sections', null, ['placement' => 'sidebar']))->assertUnprocessable();
        $this->assertSame(['nav-only'], array_column($this->getJson($this->url('classified/sections', null, ['placement' => 'nav']))->json('data'), 'slug'));
    }

    // ── Category landing pages ───────────────────────────────────────────────

    public function test_category_landing_resolves_by_slug_or_id_with_parent_children_and_its_own_ads(): void
    {
        $root = ClassifiedCategory::factory()->create(['slug' => 'real-estate', 'description_ar' => 'وصف']);
        $child = ClassifiedCategory::factory()->childOf($root)->create(['slug' => 'apartments']);
        $this->listingIn($root, 'Villa');
        $this->listingIn($child, 'Flat');
        $this->listingIn(ClassifiedCategory::factory()->create(), 'Unrelated');

        $bySlug = $this->getJson($this->url('browse/classified/real-estate'))->assertOk();
        $this->assertSame($root->id, $bySlug->json('data.category.id'));
        $this->assertSame(['apartments'], array_column($bySlug->json('data.category.children'), 'slug'));
        $this->assertSame('وصف', $bySlug->json('data.category.description.ar'));
        $this->assertEqualsCanonicalizing(['Villa', 'Flat'], array_column($bySlug->json('data.listings.items'), 'title_en'));

        $this->getJson($this->url('browse/classified/'.$root->id))->assertOk()->assertJsonPath('data.category.slug', 'real-estate');

        $sub = $this->getJson($this->url('browse/classified/apartments'))->assertOk();
        $this->assertSame('real-estate', $sub->json('data.category.parent.slug'));
        $this->assertSame(['Flat'], array_column($sub->json('data.listings.items'), 'title_en'));
    }

    public function test_a_disabled_category_is_a_404_and_its_ads_leave_every_public_listing_without_being_deleted(): void
    {
        $root = ClassifiedCategory::factory()->create(['slug' => 'real-estate']);
        $child = ClassifiedCategory::factory()->childOf($root)->create(['slug' => 'apartments']);
        $kept = ClassifiedCategory::factory()->create(['slug' => 'cars']);
        $flat = $this->listingIn($child, 'Flat');
        $this->listingIn($kept, 'Sedan');

        $this->getJson($this->url('listings/classified/'.$flat->listing_number))->assertOk();

        $root->update(['is_active' => false]);

        $this->getJson($this->url('browse/classified/real-estate'))->assertNotFound();
        $this->getJson($this->url('browse/classified/'.$root->id))->assertNotFound();
        $this->getJson($this->url('browse/classified/apartments'))->assertNotFound();
        $this->assertSame(['Sedan'], array_column($this->getJson($this->url('classified'))->json('data.listings.items'), 'title_en'));
        $this->assertSame(['Sedan'], array_column($this->getJson($this->url('classified/map-pins'))->json('data'), 'title'));
        $this->getJson($this->url('listings/classified/'.$flat->listing_number))->assertNotFound();

        // Nothing was deleted: the ad keeps its status and owner.
        $this->assertDatabaseHas('classified_listings', ['id' => $flat->id, 'status' => 'active', 'classified_category_id' => $child->id]);

        $root->update(['is_active' => true]);
        $this->getJson($this->url('browse/classified/apartments'))->assertOk();
        $this->getJson($this->url('listings/classified/'.$flat->listing_number))->assertOk();
    }

    public function test_a_category_limited_to_other_countries_is_a_404_here(): void
    {
        $other = $this->extraCountry('OM', 'Oman');
        $category = ClassifiedCategory::factory()->create(['slug' => 'oman-only']);
        $category->countries()->sync([$other->id]);
        ClassifiedCategory::flushStorefrontCaches();
        $this->listingIn($category, 'Omani ad', $other);

        $this->getJson($this->url('browse/classified/oman-only'))->assertNotFound();
        $this->getJson($this->url('browse/classified/oman-only', $other))->assertOk();
        $this->assertSame([], $this->getJson($this->url('classified'))->json('data.listings.items'));
    }

    // ── Writes ───────────────────────────────────────────────────────────────

    public function test_new_ads_cannot_be_created_in_a_disabled_or_unavailable_category(): void
    {
        Bus::fake();
        $other = $this->extraCountry('OM', 'Oman');
        $disabled = ClassifiedCategory::factory()->inactive()->create();
        $omanOnly = ClassifiedCategory::factory()->create();
        $omanOnly->countries()->sync([$other->id]);
        ClassifiedCategory::flushStorefrontCaches();

        $payload = fn (ClassifiedCategory $category) => [
            'classified_category_id' => $category->id, 'country_id' => $this->home->id, 'listing_purpose' => 'sale',
            'title_en' => 'Ad', 'title_ar' => 'إعلان', 'price' => 10, 'currency' => 'AED',
        ];

        foreach ([$disabled, $omanOnly] as $category) {
            try {
                app(ClassifiedListingService::class)->create($this->scenario->vendor, $payload($category));
                $this->fail('A category that is not available must not accept new ads.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('classified_category_id', $e->errors());
            }
        }

        $this->assertSame(0, ClassifiedListing::count());
        $this->assertTrue(app(ClassifiedSectionService::class)->isVisible($omanOnly->id, $other));
    }

    public function test_deactivating_a_category_keeps_signed_contracts_and_existing_listings(): void
    {
        $permissions = ['classifieds.view', 'categories.edit', 'vendors.assigned_only'];
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'admin']);
        }
        $admin = Admin::factory()->create();
        $admin->givePermissionTo($permissions);

        $vendor = $this->scenario->vendor;
        $vendor->update(['global_status' => 'active', 'onboarding_completed_at' => now()]);
        $template = $this->contractTemplate('classified', 'Property terms');
        $category = ClassifiedCategory::factory()->create(['contract_template_id' => $template->id, 'requires_contract' => true]);
        $contract = app(CategoryContractService::class)->sign($vendor, null, 'classified', $category->id, 'en', 'Owner', '127.0.0.1', 'phpunit');
        $listing = $this->listingIn($category);

        $this->actingAs($admin, 'admin')->postJson(route('admin.classifieds.categories.toggle', $category))->assertOk()->assertJsonPath('is_active', false);

        $this->assertSame('active', $contract->fresh()->status);
        $this->assertSame(1, VendorContract::where('classified_category_id', $category->id)->count());
        $this->assertSame('active', $listing->fresh()->status->value);
        $this->assertSame($template->id, $category->fresh()->contract_template_id);
        $this->getJson($this->url('browse/classified/'.$category->slug))->assertNotFound();
    }
}
