<?php

namespace Tests\Feature\Customer;

use App\Models\Category;
use App\Models\CountryCategory;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\PageSection;
use App\Models\Slug;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * Client #7/#8 — what each category flag means on the storefront API:
 *  - Active off  → category page 404 (direct link too), subcategories go with it;
 *  - Visible off → gone from the navigation tree, direct link still works;
 *  - Featured    → fills the homepage "category pills" block when it has no hand-picked categories;
 *  - Country exception (category form "only / except" mode) → same 404 + nav removal in that country, inherited by subcategories.
 */
class CategoryStorefrontFlagsTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $s;

    private string $base;

    protected function setUp(): void
    {
        parent::setUp();
        $this->s = MarketplaceScenario::make()->build();
        if (empty($this->s->country->site_code)) {
            $this->s->country->update(['site_code' => strtolower($this->s->country->iso_code_2)]);
        }
        $this->base = "/api/customer/v1/{$this->s->country->site_code}";
        Slug::upsertFor($this->s->category, $this->s->category->slug);
    }

    private function categoryPage(Category $category): TestResponse
    {
        return $this->getJson($this->base.'/products?'.http_build_query(['category' => $category->slug]));
    }

    /**
     * @return list<string>
     */
    private function navCategoryIds(): array
    {
        $ids = [];
        $walk = function (array $nodes) use (&$walk, &$ids): void {
            foreach ($nodes as $node) {
                $ids[] = $node['id'];
                $walk($node['children'] ?? []);
            }
        };
        $walk($this->getJson($this->base.'/categories')->assertOk()->json('data'));

        return $ids;
    }

    private function child(Category $parent, array $attributes = []): Category
    {
        $child = Category::factory()->create(['parent_id' => $parent->id] + $attributes);
        Slug::upsertFor($child, $child->slug);

        return $child;
    }

    public function test_active_category_lists_its_products(): void
    {
        $this->categoryPage($this->s->category)->assertOk();
        $this->assertGreaterThan(0, $this->categoryPage($this->s->category)->json('data.meta.total'));
    }

    public function test_inactive_category_page_is_not_found_even_by_direct_link(): void
    {
        $child = $this->child($this->s->category);
        $this->s->category->update(['is_active' => false]);

        $this->categoryPage($this->s->category)->assertNotFound();
        $this->getJson("{$this->base}/browse/product/{$this->s->category->id}")->assertNotFound();
        $this->categoryPage($child)->assertNotFound();
        $this->assertNotContains($this->s->category->id, $this->navCategoryIds());
    }

    public function test_hidden_category_leaves_the_menu_but_its_link_still_works(): void
    {
        $this->s->category->update(['is_visible' => false]);

        $this->assertNotContains($this->s->category->id, $this->navCategoryIds());
        $this->categoryPage($this->s->category)->assertOk();
    }

    public function test_country_exception_hides_category_and_its_subcategories_in_that_country_only(): void
    {
        $child = $this->child($this->s->category);
        $this->assertContains($child->id, $this->navCategoryIds());

        CountryCategory::create(['country_id' => $this->s->country->id, 'category_id' => $this->s->category->id, 'is_available' => false]);

        $this->categoryPage($this->s->category)->assertNotFound();
        $this->categoryPage($child)->assertNotFound();
        $this->assertNotContains($this->s->category->id, $this->navCategoryIds());
        $this->assertNotContains($child->id, $this->navCategoryIds());
    }

    public function test_featured_categories_fill_the_homepage_category_pills_block(): void
    {
        $featured = Category::factory()->create(['is_featured' => true]);
        $featuredButInactive = Category::factory()->create(['is_featured' => true, 'is_active' => false]);
        $notFeatured = Category::factory()->create(['is_featured' => false]);

        $page = Page::create([
            'country_id' => $this->s->country->id, 'page_type' => 'home', 'name' => 'Home', 'slug' => 'home',
            'status' => 'published', 'published_at' => now(), 'version' => 1, 'is_default' => true,
        ]);
        $section = PageSection::create(['page_id' => $page->id, 'name' => 'Main', 'position' => 0, 'is_visible' => true, 'layout' => 'stack']);
        PageBlock::create([
            'page_id' => $page->id, 'section_id' => $section->id, 'column_index' => 0, 'block_type' => 'category_pills',
            'position' => 0, 'config' => ['max_items' => 10, 'title_en' => 'Shop by category'], 'is_visible' => true,
            'device_target' => 'all', 'audience' => 'all', 'created_by_admin_id' => $this->s->vendor->id,
        ]);

        $json = json_encode($this->getJson($this->base.'/home')->assertOk()->json());

        $this->assertStringContainsString($featured->id, $json);
        $this->assertStringNotContainsString($featuredButInactive->id, $json);
        $this->assertStringNotContainsString($notFeatured->id, $json);
    }
}
