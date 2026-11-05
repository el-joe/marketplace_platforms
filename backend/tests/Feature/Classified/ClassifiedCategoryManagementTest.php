<?php

namespace Tests\Feature\Classified;

use App\Models\Admin;
use App\Models\ClassifiedCategory;
use App\Models\ClassifiedListing;
use App\Models\Customer;
use App\Models\File;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * Requirement 8 (admin): dynamic classified categories with image, countries, placements, hierarchy rules,
 * slug uniqueness, safe deletion and permissions.
 */
class ClassifiedCategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $scenario;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);
        Storage::fake('public');

        foreach (['classifieds.view', 'categories.create', 'categories.edit', 'categories.delete', 'vendors.assigned_only'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'admin']);
        }

        $this->scenario = MarketplaceScenario::make()->build();
        $this->admin = Admin::factory()->create();
        $this->admin->givePermissionTo(['classifieds.view', 'categories.create', 'categories.edit', 'categories.delete']);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return $overrides + ['name_en' => 'Real Estate', 'name_ar' => 'عقارات', 'is_active' => 1, 'sort_order' => 0];
    }

    private function store(array $overrides = [])
    {
        return $this->actingAs($this->admin, 'admin')
            ->post(route('admin.classifieds.categories.store'), $this->payload($overrides), ['Accept' => 'application/json']);
    }

    private function listingIn(ClassifiedCategory $category): ClassifiedListing
    {
        return ClassifiedListing::create([
            'listing_number' => 'CL-'.Str::upper(Str::random(8)), 'seller_type' => Customer::class,
            'seller_id' => $this->scenario->customer->id, 'classified_category_id' => $category->id,
            'country_id' => $this->scenario->country->id, 'listing_purpose' => 'sale', 'title_en' => 'Ad', 'title_ar' => 'إعلان',
            'price' => 10, 'currency' => 'AED', 'price_negotiable' => false, 'status' => 'active',
        ]);
    }

    public function test_admin_creates_a_category_with_image_countries_and_placements(): void
    {
        $oman = $this->scenario->country;

        $this->store([
            'description_en' => 'Homes for sale', 'description_ar' => 'منازل للبيع',
            'image' => UploadedFile::fake()->image('cover.jpg', 600, 400),
            'placements' => ['nav', 'home'], 'country_ids' => [$oman->id],
        ])->assertCreated();

        $category = ClassifiedCategory::sole();
        $this->assertSame('real-estate', $category->slug);
        $this->assertSame(['nav', 'home'], $category->placementList());
        $this->assertSame([$oman->id], $category->countries()->pluck('countries.id')->all());
        $this->assertSame('Homes for sale', $category->description_en);

        $file = File::where('model_type', ClassifiedCategory::class)->where('model_id', $category->id)->sole();
        Storage::disk('public')->assertExists($file->path);
        $this->assertNotNull($category->fresh()->image_url);
    }

    public function test_a_category_created_without_placements_keeps_the_legacy_default(): void
    {
        $this->store()->assertCreated();

        $category = ClassifiedCategory::sole();
        $this->assertNull($category->placements);
        $this->assertSame(['nav', 'landing'], $category->placementList());
    }

    public function test_slugs_are_validated_and_generated_uniquely(): void
    {
        ClassifiedCategory::factory()->create(['slug' => 'cars']);

        $this->store(['slug' => 'cars'])->assertUnprocessable()->assertJsonValidationErrors('slug');
        $this->store(['slug' => 'find'])->assertUnprocessable()->assertJsonValidationErrors('slug');
        $this->store(['slug' => 'Has Spaces'])->assertUnprocessable()->assertJsonValidationErrors('slug');
        $this->store(['slug' => (string) Str::uuid()])->assertUnprocessable()->assertJsonValidationErrors('slug');

        $this->store(['name_en' => 'Cars'])->assertCreated();
        $this->store(['name_en' => 'Cars'])->assertCreated();

        $this->assertEqualsCanonicalizing(['cars', 'cars-2', 'cars-3'], ClassifiedCategory::pluck('slug')->all());
    }

    public function test_hierarchy_has_two_levels_and_cycles_are_rejected(): void
    {
        $root = ClassifiedCategory::factory()->create();
        $child = ClassifiedCategory::factory()->childOf($root)->create();

        $this->store(['name_en' => 'Apartments', 'parent_id' => $root->id])->assertCreated();
        $this->store(['name_en' => 'Too deep', 'parent_id' => $child->id])->assertUnprocessable()->assertJsonValidationErrors('parent_id');

        $update = fn (ClassifiedCategory $category, string $parentId) => $this->actingAs($this->admin, 'admin')
            ->putJson(route('admin.classifieds.categories.update', $category), $this->payload(['name_en' => $category->name_en, 'parent_id' => $parentId]));

        $update($root, $child->id)->assertUnprocessable();
        $update($root, $root->id)->assertUnprocessable();
        $this->assertNull($root->fresh()->parent_id);
    }

    public function test_update_keeps_placements_and_countries_when_they_are_not_sent_and_replaces_the_image(): void
    {
        $category = ClassifiedCategory::factory()->placedOn(['home'])->create();
        $category->countries()->sync([$this->scenario->country->id]);
        $put = fn (array $extra = []) => $this->actingAs($this->admin, 'admin')
            ->post(route('admin.classifieds.categories.update', $category), $this->payload(['name_en' => $category->name_en, '_method' => 'PUT'] + $extra), ['Accept' => 'application/json']);

        $put(['image' => UploadedFile::fake()->image('a.jpg')])->assertOk();
        $first = File::where('model_id', $category->id)->sole();

        $this->assertSame(['home'], $category->fresh()->placementList());
        $this->assertCount(1, $category->countries);

        $put(['image' => UploadedFile::fake()->image('b.png')])->assertOk();
        $second = File::where('model_id', $category->id)->sole();
        $this->assertNotSame($first->id, $second->id);
        Storage::disk('public')->assertMissing($first->path);

        $put(['remove_image' => 1, 'placements_submitted' => 1, 'countries_submitted' => 1])->assertOk();
        $this->assertSame(0, File::where('model_id', $category->id)->count());
        $this->assertSame([], $category->fresh()->placementList(), 'an explicitly empty selection hides it from every surface');
        $this->assertCount(0, $category->fresh()->countries, 'no countries means every country');
    }

    public function test_image_must_be_a_real_image(): void
    {
        $this->store(['image' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')])
            ->assertUnprocessable()->assertJsonValidationErrors('image');
    }

    public function test_a_category_with_records_cannot_be_deleted_but_can_be_deactivated(): void
    {
        $root = ClassifiedCategory::factory()->create();
        $child = ClassifiedCategory::factory()->childOf($root)->create();
        $listing = $this->listingIn($child);

        $this->actingAs($this->admin, 'admin')->deleteJson(route('admin.classifieds.categories.destroy', $root))
            ->assertUnprocessable()->assertJsonPath('blockers.children', 1);

        $this->actingAs($this->admin, 'admin')->deleteJson(route('admin.classifieds.categories.destroy', $child))
            ->assertUnprocessable()->assertJsonPath('blockers.listings', 1)
            ->assertJsonStructure(['message', 'blockers']);

        $this->actingAs($this->admin, 'admin')->postJson(route('admin.classifieds.categories.toggle', $child))
            ->assertOk()->assertJsonPath('is_active', false);

        $this->assertDatabaseHas('classified_listings', ['id' => $listing->id, 'status' => 'active']);
        $this->assertDatabaseHas('classified_categories', ['id' => $child->id, 'is_active' => 0]);
    }

    public function test_an_unused_category_is_deleted_with_its_image_and_country_scope(): void
    {
        $this->store(['image' => UploadedFile::fake()->image('a.jpg'), 'country_ids' => [$this->scenario->country->id]])->assertCreated();
        $category = ClassifiedCategory::sole();
        $path = File::where('model_id', $category->id)->value('path');

        $this->actingAs($this->admin, 'admin')->deleteJson(route('admin.classifieds.categories.destroy', $category))->assertOk();

        $this->assertSame(0, ClassifiedCategory::count());
        $this->assertSame(0, File::where('model_id', $category->id)->count());
        Storage::disk('public')->assertMissing($path);
        $this->assertDatabaseCount('classified_category_country', 0);
    }

    public function test_names_in_both_languages_are_required(): void
    {
        $this->store(['name_ar' => ''])->assertUnprocessable()->assertJsonValidationErrors('name_ar');
        $this->store(['name_en' => ''])->assertUnprocessable()->assertJsonValidationErrors('name_en');
    }

    public function test_admin_pages_render_with_placements_countries_and_hidden_badges(): void
    {
        $root = ClassifiedCategory::factory()->placedOn(['home'])->create(['name_en' => 'Visible root']);
        ClassifiedCategory::factory()->childOf($root)->inactive()->create(['name_en' => 'Hidden child']);

        $this->actingAs($this->admin, 'admin')->get(route('admin.classifieds.categories.index'))
            ->assertOk()
            ->assertSee('Visible root')
            ->assertSee('Hidden child')
            ->assertSee(__('classified.admin.hidden_badge'))
            ->assertSee(__('classified.admin.placement_title'));
    }

    public function test_permissions_are_enforced_on_the_backend(): void
    {
        $category = ClassifiedCategory::factory()->create();
        $viewer = Admin::factory()->create();
        $viewer->givePermissionTo('classifieds.view');
        $stranger = Admin::factory()->create();

        $this->actingAs($viewer, 'admin')->get(route('admin.classifieds.categories.index'))->assertOk();
        $this->actingAs($viewer, 'admin')->postJson(route('admin.classifieds.categories.store'), $this->payload())->assertForbidden();
        $this->actingAs($viewer, 'admin')->putJson(route('admin.classifieds.categories.update', $category), $this->payload())->assertForbidden();
        $this->actingAs($viewer, 'admin')->postJson(route('admin.classifieds.categories.toggle', $category))->assertForbidden();
        $this->actingAs($viewer, 'admin')->postJson(route('admin.classifieds.categories.reorder'), ['ids' => [$category->id]])->assertForbidden();
        $this->actingAs($viewer, 'admin')->deleteJson(route('admin.classifieds.categories.destroy', $category))->assertForbidden();

        $this->actingAs($stranger, 'admin')->get(route('admin.classifieds.categories.index'))->assertForbidden();
        $this->assertDatabaseHas('classified_categories', ['id' => $category->id, 'is_active' => 1]);
    }
}
