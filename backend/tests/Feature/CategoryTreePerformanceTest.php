<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Country;
use App\Models\CountryCategory;
use App\Models\Product;
use App\Services\Customer\CategoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * enhancement.md P-21: categories list/tree performance.
 *
 * Builds ~130 categories across 3 levels of nesting (roots -> children ->
 * grandchildren), a handful of brands and products spread across them, and
 * asserts the nav tree / browse tree stay at or under 6 queries each, that
 * counts are correct against a brute-force calculation (with descendant
 * roll-up), that the nav tree is genuinely cheaper than the browse tree,
 * and that the cache invalidates on category CRUD.
 */
class CategoryTreePerformanceTest extends TestCase
{
    use RefreshDatabase;

    private Country $country;

    protected function setUp(): void
    {
        parent::setUp();

        $this->country = Country::factory()->create(['site_code' => 'zz-test', 'is_active' => true]);
    }

    /**
     * @return array{0: \Illuminate\Support\Collection<int, Category>, 1: \Illuminate\Support\Collection<int, Brand>}
     */
    private function buildCategoryForest(): array
    {
        $brands = collect(range(1, 6))->map(fn (int $i) => Brand::create([
            'name_ar' => "براند {$i}",
            'name_en' => "Brand {$i}",
            'slug' => 'brand-' . $i . '-' . Str::random(6),
            'is_active' => true,
        ]));

        $roots = Category::factory()->count(8)->create(['parent_id' => null]);

        $allCategories = collect($roots);

        foreach ($roots as $root) {
            $children = Category::factory()->count(6)->create(['parent_id' => $root->id]);
            $allCategories = $allCategories->merge($children);

            foreach ($children as $child) {
                $grandchildren = Category::factory()->count(2)->create(['parent_id' => $child->id]);
                $allCategories = $allCategories->merge($grandchildren);
            }
        }

        // 8 + 8*6 + 8*6*2 = 8 + 48 + 96 = 152 categories.
        $this->assertGreaterThanOrEqual(150, $allCategories->count());

        // Every category available in this country via an explicit row (some
        // categories also work with no row at all — "available by default").
        foreach ($allCategories->take(100) as $category) {
            CountryCategory::create([
                'country_id' => $this->country->id,
                'category_id' => $category->id,
                'is_available' => true,
            ]);
        }

        // Products + buy-box rows spread across leaf categories, several
        // brands each, so both product_count and brand roll-up are non-trivial.
        $leafCategories = $allCategories->filter(fn (Category $c) => $c->parent_id !== null && $allCategories->firstWhere('parent_id', $c->id) === null);

        $rows = [];
        foreach ($leafCategories->take(40) as $i => $category) {
            $brand = $brands[$i % $brands->count()];
            $product = Product::factory()->create([
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'status' => 'active',
            ]);

            $rows[] = [
                'product_id' => $product->id,
                'country_id' => $this->country->id,
                'listing_type' => 'admin',
                'listing_id' => (string) Str::uuid(),
                'variant_id' => (string) Str::uuid(),
                'price' => 1000,
                'compare_at_price' => null,
                'min_price' => 1000,
                'max_price' => 1000,
                'seller_count' => 1,
                'admin_listing_count' => 1,
                'total_stock' => 10,
                'rating_avg' => 0,
                'rating_count' => 0,
                'fulfillment_model' => 'fbn',
                'shipping_method_id' => null,
                'is_express' => false,
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'total_sold' => 0,
                'score' => 0,
                'updated_at' => now(),
            ];
        }

        DB::table('product_country_buybox')->insert($rows);

        return [$allCategories, $brands];
    }

    /** Brute-force product/brand counts per category, including descendant roll-up. */
    private function bruteForceCounts(\Illuminate\Support\Collection $allCategories): array
    {
        $childrenOf = fn (string $id) => $allCategories->where('parent_id', $id);

        $descendants = function (string $id) use (&$descendants, $childrenOf) {
            $ids = [$id];
            foreach ($childrenOf($id) as $child) {
                $ids = array_merge($ids, $descendants($child->id));
            }
            return $ids;
        };

        $counts = [];
        foreach ($allCategories as $category) {
            $subtreeIds = $descendants($category->id);
            $productCount = DB::table('product_country_buybox')
                ->where('country_id', $this->country->id)
                ->whereIn('category_id', $subtreeIds)
                ->count();
            $counts[$category->id] = $productCount;
        }

        return $counts;
    }

    public function test_browse_tree_cold_query_count_and_correctness(): void
    {
        [$allCategories, $brands] = $this->buildCategoryForest();

        $bruteForce = $this->bruteForceCounts($allCategories);

        /** @var CategoryService $service */
        $service = app(CategoryService::class);

        DB::enableQueryLog();
        $tree = $service->getBrowseTree($this->country);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(6, $queries, "Browse tree cold call ran {$queries} queries, expected <= 6.");

        // Flatten and check counts against brute force for a sample of nodes.
        $flat = [];
        $flatten = function (array $nodes) use (&$flatten, &$flat) {
            foreach ($nodes as $node) {
                $flat[$node['id']] = $node;
                $flatten($node['children']);
            }
        };
        $flatten($tree);

        $checked = 0;
        foreach ($allCategories->take(20) as $category) {
            if (!isset($flat[$category->id])) {
                continue;
            }
            $this->assertSame(
                $bruteForce[$category->id],
                $flat[$category->id]['product_count'],
                "product_count mismatch for category {$category->id}"
            );
            $checked++;
        }
        $this->assertGreaterThan(0, $checked);

        // Root nodes carry attributes key (heavy payload) even when empty.
        $this->assertArrayHasKey('attributes', reset($tree));
    }

    public function test_nav_tree_is_cheaper_than_browse_tree_and_has_no_attributes(): void
    {
        $this->buildCategoryForest();

        /** @var CategoryService $service */
        $service = app(CategoryService::class);

        DB::enableQueryLog();
        $navTree = $service->getNavTree($this->country);
        $navQueries = collect(DB::getQueryLog())->pluck('query')->all();
        DB::flushQueryLog();

        $browseTree = $service->getBrowseTree($this->country);
        $browseQueries = collect(DB::getQueryLog())->pluck('query')->all();
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(6, count($navQueries));
        $this->assertLessThanOrEqual(6, count($browseQueries));

        // The nav tree never touches category_attributes at all — that's
        // the query cost the heavy browse tree pays and the nav tree does not.
        $navAttributeQueries = collect($navQueries)->filter(fn ($sql) => str_contains($sql, 'category_attributes'));
        $browseAttributeQueries = collect($browseQueries)->filter(fn ($sql) => str_contains($sql, 'category_attributes'));

        $this->assertCount(0, $navAttributeQueries, 'nav tree must not query category_attributes');
        $this->assertGreaterThan(0, $browseAttributeQueries->count(), 'browse tree must query category_attributes');

        $productNode = collect($navTree)->firstWhere('type', 'product');
        $this->assertArrayNotHasKey('attributes', $productNode);
    }

    public function test_cache_invalidates_on_category_update(): void
    {
        $this->buildCategoryForest();

        /** @var CategoryService $service */
        $service = app(CategoryService::class);

        $navTree = $service->getNavTree($this->country);
        $productNode = collect($navTree)->firstWhere('type', 'product');
        $category = Category::find($productNode['id']);

        $category->update(['name_en' => 'Renamed Category XYZ']);

        $navTreeAfter = $service->getNavTree($this->country);
        $updatedNode = collect($navTreeAfter)->firstWhere('id', $category->id);

        $this->assertSame('Renamed Category XYZ', $updatedNode['name']['en']);
    }

    public function test_cache_invalidates_on_country_category_change(): void
    {
        [$allCategories] = $this->buildCategoryForest();

        $extra = Category::factory()->create(['parent_id' => null]);

        $countryCategory = CountryCategory::create([
            'country_id' => $this->country->id,
            'category_id' => $extra->id,
            'is_available' => false,
        ]);

        /** @var CategoryService $service */
        $service = app(CategoryService::class);

        $before = $service->getNavTree($this->country);
        $idsBefore = collect($before)->pluck('id')->all();
        $this->assertNotContains($extra->id, $idsBefore);

        $countryCategory->update(['is_available' => true]);

        $after = $service->getNavTree($this->country);
        $idsAfter = collect($after)->pluck('id')->all();
        $this->assertContains($extra->id, $idsAfter);
    }
}
