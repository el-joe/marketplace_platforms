<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Client notes #3 / #4 (2026-11-22): the admin panel showed English product and
 * category names while in Arabic, and pickers only matched one language.
 */
class AdminLocalizedNamesTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'vendors.assigned_only', 'guard_name' => 'admin']);

        $this->admin = Admin::factory()->create();
        $this->admin->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'admin']));
    }

    public function test_products_datatable_shows_the_name_in_the_panel_locale(): void
    {
        $category = Category::factory()->create(['name_en' => 'Automotive', 'name_ar' => 'السيارات']);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name_en' => 'Car Wax '.Str::random(6),
            'name_ar' => 'شمع السيارات '.Str::random(6),
        ]);

        $arabicRow = $this->productRow($product, 'ar');
        $this->assertSame(e($product->name_ar), $arabicRow['name']);
        $this->assertSame('السيارات', $arabicRow['category']);

        $englishRow = $this->productRow($product, 'en');
        $this->assertSame(e($product->name_en), $englishRow['name']);
        $this->assertSame('Automotive', $englishRow['category']);
    }

    public function test_products_datatable_falls_back_to_english_when_the_arabic_name_is_empty(): void
    {
        $product = Product::factory()->create(['name_en' => 'Only English '.Str::random(6), 'name_ar' => '']);

        $this->assertSame(e($product->name_en), $this->productRow($product, 'ar')['name']);
    }

    public function test_products_search_filter_matches_the_arabic_name_from_the_english_panel(): void
    {
        $product = Product::factory()->create(['name_en' => 'Brake Pads '.Str::random(6), 'name_ar' => 'فحمات فرامل '.Str::random(6)]);

        $rows = $this->withSession(['locale' => 'en'])
            ->actingAs($this->admin, 'admin')
            ->postJson(route('admin.products.datatable'), ['draw' => 1, 'start' => 0, 'length' => 25, 'search' => 'فحمات فرامل'])
            ->assertOk()
            ->json('data');

        $this->assertContains($product->id, array_column($rows, 'id'));
    }

    public function test_category_picker_search_matches_arabic_and_labels_by_locale(): void
    {
        $category = Category::factory()->create(['name_en' => 'Automotive '.Str::random(4), 'name_ar' => 'السيارات '.Str::random(4)]);

        $arabicResults = $this->withSession(['locale' => 'ar'])
            ->actingAs($this->admin, 'admin')
            ->getJson(route('admin.categories.search', ['q' => 'السيارات']))
            ->assertOk()
            ->json('results');

        $this->assertContains(['id' => $category->id, 'text' => $category->name_ar], $arabicResults);

        $englishResults = $this->withSession(['locale' => 'en'])
            ->actingAs($this->admin, 'admin')
            ->getJson(route('admin.categories.search', ['q' => 'السيارات']))
            ->assertOk()
            ->json('results');

        $this->assertContains(['id' => $category->id, 'text' => $category->name_en], $englishResults);
    }

    public function test_category_picker_ignores_inactive_categories(): void
    {
        $inactive = Category::factory()->create(['name_en' => 'Hidden '.Str::random(6), 'is_active' => false]);

        $results = $this->withSession(['locale' => 'en'])
            ->actingAs($this->admin, 'admin')
            ->getJson(route('admin.categories.search', ['q' => $inactive->name_en]))
            ->assertOk()
            ->json('results');

        $this->assertNotContains($inactive->id, array_column($results, 'id'));
    }

    /**
     * @return array<string, mixed>
     */
    private function productRow(Product $product, string $locale): array
    {
        $rows = $this->withSession(['locale' => $locale])
            ->actingAs($this->admin, 'admin')
            ->postJson(route('admin.products.datatable'), ['draw' => 1, 'start' => 0, 'length' => 100, 'search' => $product->name_en])
            ->assertOk()
            ->json('data');

        $row = collect($rows)->firstWhere('id', $product->id);
        $this->assertNotNull($row, 'Product row missing from datatable response.');

        return $row;
    }
}
