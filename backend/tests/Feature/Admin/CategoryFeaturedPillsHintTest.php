<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Country;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\PageSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Client retest R9: Featured categories only show through a home-page "Category pills" block, so the
 * categories list warns (read-only check) when a published home page has no such block.
 */
class CategoryFeaturedPillsHintTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    private Page $home;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['categories.view', 'pages.view', 'vendors.assigned_only'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'admin']);
        }
        $this->admin = Admin::factory()->create();
        $this->admin->givePermissionTo(['categories.view', 'pages.view']);

        $country = Country::factory()->create(['name_en' => 'Pillsland', 'name_ar' => 'بلد الأزرار']);
        $this->home = Page::create([
            'country_id' => $country->id, 'page_type' => 'home', 'name' => 'Home', 'slug' => 'home-pills',
            'status' => 'published', 'published_at' => now(), 'version' => 1, 'is_default' => true,
        ]);
    }

    private function addPillsBlock(bool $handPicked = false): void
    {
        $section = PageSection::create(['page_id' => $this->home->id, 'name' => 'Main', 'position' => 0, 'is_visible' => true, 'layout' => 'stack']);
        $block = PageBlock::create([
            'page_id' => $this->home->id, 'section_id' => $section->id, 'column_index' => 0, 'block_type' => 'category_pills',
            'position' => 0, 'config' => ['max_items' => 10], 'is_visible' => true,
            'device_target' => 'all', 'audience' => 'all', 'created_by_admin_id' => $this->admin->id,
        ]);

        if ($handPicked) {
            $block->blockCategories()->create(['category_id' => Category::factory()->create()->id, 'position' => 0]);
        }
    }

    private function hintTitle(): string
    {
        return __('admin.categories.featured_no_pills_title');
    }

    public function test_warns_with_the_country_and_page_builder_link_when_a_home_page_has_no_pills_block(): void
    {
        Category::factory()->create(['is_featured' => true]);

        $this->actingAs($this->admin, 'admin')->get(route('admin.categories.index'))
            ->assertOk()
            ->assertSee($this->hintTitle())
            ->assertSee(app()->getLocale() === 'ar' ? 'بلد الأزرار' : 'Pillsland')
            ->assertSee(route('admin.page-builder.index'), false);
    }

    public function test_warns_when_the_only_pills_block_uses_hand_picked_categories(): void
    {
        Category::factory()->create(['is_featured' => true]);
        $this->addPillsBlock(handPicked: true);

        $this->actingAs($this->admin, 'admin')->get(route('admin.categories.index'))
            ->assertOk()
            ->assertSee($this->hintTitle());
    }

    public function test_no_warning_when_the_home_page_has_an_auto_pills_block_or_nothing_is_featured(): void
    {
        $this->actingAs($this->admin, 'admin')->get(route('admin.categories.index'))
            ->assertOk()
            ->assertDontSee($this->hintTitle());

        Category::factory()->create(['is_featured' => true]);
        $this->addPillsBlock();

        $this->actingAs($this->admin, 'admin')->get(route('admin.categories.index'))
            ->assertOk()
            ->assertDontSee($this->hintTitle());
    }
}
