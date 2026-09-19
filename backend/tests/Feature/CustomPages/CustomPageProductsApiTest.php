<?php

namespace Tests\Feature\CustomPages;

use App\Models\Category;
use App\Models\CustomPage;
use App\Models\Slug;
use App\Services\CustomPageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

class CustomPageProductsApiTest extends TestCase
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
        $this->base = "/api/customer/v1/{$this->s->country->site_code}/products";
    }

    private function page(array $a = [], array $cats = []): string
    {
        $p = CustomPage::create($a + ['name_en' => 'P', 'name_ar' => 'ص', 'is_active' => true, 'has_filters' => true]);
        $slug = "pg-" . strtolower(\Illuminate\Support\Str::random(10));
        Slug::create(['slug_url' => $slug, 'sluggable_type' => CustomPage::class, 'sluggable_id' => $p->id] + $this->slugExtras());
        if ($cats) {
            app(CustomPageService::class)->syncCategories($p, $cats);
        }
        return $slug;
    }

    private function slugExtras(): array
    {
        return [];
    }

    private function api(string $slug, array $q = [])
    {
        return $this->getJson($this->base . '?' . http_build_query(['category' => $slug] + $q));
    }

    private function types($r): array
    {
        return collect($r->json('data.items'))->pluck('listing_type')->unique()->sort()->values()->all();
    }

    public function test_admin_only(): void
    {
        $r = $this->api($this->page(['listing_types' => ['admin'], 'all_categories' => true]))->assertOk();
        $this->assertSame(['admin'], $this->types($r));
        $this->assertSame(1, $r->json('data.meta.total'));
        $this->assertSame(['admin'], $r->json('data.category.listing_types'));
    }

    public function test_vendor_only_and_marketer_only(): void
    {
        $r = $this->api($this->page(['listing_types' => ['vendor'], 'all_categories' => true]))->assertOk();
        $this->assertSame(['vendor'], $this->types($r));
        $this->assertSame(2, $r->json('data.meta.total'));
        $r = $this->api($this->page(['listing_types' => ['marketer'], 'all_categories' => true]))->assertOk();
        $this->assertSame(['marketer'], $this->types($r));
        $this->assertSame(1, $r->json('data.meta.total'));
        $this->assertNotEmpty($r->json('data.items.0.referral_code'));
    }

    public function test_all_types_and_pagination(): void
    {
        $slug = $this->page(['all_categories' => true]);
        $r = $this->api($slug)->assertOk();
        $this->assertSame(4, $r->json('data.meta.total'));
        $this->assertSame(['admin', 'marketer', 'vendor'], $this->types($r));
        $p1 = $this->api($slug, ['per_page' => 3])->assertOk();
        $p2 = $this->api($slug, ['per_page' => 3, 'page' => 2])->assertOk();
        $this->assertSame(2, $p1->json('data.meta.last_page'));
        $this->assertCount(3, $p1->json('data.items'));
        $this->assertCount(1, $p2->json('data.items'));
    }

    public function test_category_subset_and_zero_category_page(): void
    {
        $slug = $this->page([], [$this->s->category->id]);
        $this->assertSame(4, $this->api($slug)->assertOk()->json('data.meta.total'));
        $other = Category::create(['id' => (string) \Illuminate\Support\Str::uuid(), 'slug' => 'oth-' . uniqid(), 'name_en' => 'O', 'name_ar' => 'O', 'is_active' => true]);
        $this->assertSame(0, $this->api($this->page([], [$other->id]))->assertOk()->json('data.meta.total'));
        $r = $this->api($this->page())->assertOk();
        $this->assertSame(0, $r->json('data.meta.total'));
        $this->assertSame([], $r->json('data.items'));
    }

    public function test_filters_and_sort_with_types(): void
    {
        $slug = $this->page(['all_categories' => true]);
        $r = $this->api($slug, ['price_min' => 1100, 'price_max' => 5000])->assertOk();
        $this->assertSame(1, $r->json('data.meta.total')); // vendor fbn 1200 only
        $asc = collect($this->api($slug, ['sort' => 'price_asc'])->json('data.items'))->pluck('price')->all();
        $sorted = $asc; sort($sorted);
        $this->assertSame($sorted, $asc);
        $desc = collect($this->api($slug, ['sort' => 'price_desc'])->json('data.items'))->pluck('price')->all();
        $this->assertSame(array_reverse($sorted), $desc);
        $this->api($slug, ['sort' => 'rating', 'rating_min' => 1])->assertOk();
        $this->api($slug, ['brand' => $this->s->brand->id])->assertOk();
        $this->api($slug, ['attributes' => ['color' => ['Red']]])->assertOk();
    }

    public function test_facets_match_grid(): void
    {
        $slug = $this->page(['listing_types' => ['vendor'], 'all_categories' => true]);
        $r = $this->api($slug)->assertOk();
        $prices = collect($r->json('data.items'))->pluck('price');
        $this->assertSame((int) $prices->min(), $r->json('data.facets.price_range.min'));
        $this->assertSame((int) $prices->max(), $r->json('data.facets.price_range.max'));
    }

    public function test_inactive_and_deleted_page_404_and_guest_customer(): void
    {
        $slug = $this->page(['is_active' => false, 'all_categories' => true]);
        $this->api($slug)->assertNotFound();
        $slug2 = $this->page(['all_categories' => true]);
        CustomPage::where('id', Slug::where('slug_url', $slug2)->value('sluggable_id'))->first()->delete();
        $this->api($slug2)->assertNotFound();
        $ok = $this->page(['all_categories' => true]);
        $this->actingAs($this->s->customer, 'customer');
        $this->api($ok)->assertOk();
    }

    public function test_type_change_visible_immediately(): void
    {
        $slug = $this->page(['listing_types' => ['admin'], 'all_categories' => true]);
        $this->assertSame(1, $this->api($slug)->json('data.meta.total'));
        CustomPage::whereKey(Slug::where('slug_url', $slug)->value('sluggable_id'))->update(['listing_types' => json_encode(['vendor'])]);
        $this->assertSame(2, $this->api($slug)->json('data.meta.total'));
    }

    public function test_plain_category_browsing_unchanged(): void
    {
        $r = $this->api($this->s->category->id)->assertOk();
        $this->assertSame(['admin', 'vendor'], $this->types($r)); // legacy: no marketer block
    }

    public function test_query_count_bounded(): void
    {
        $slug = $this->page(['all_categories' => true]);
        DB::enableQueryLog();
        $this->api($slug)->assertOk();
        $this->assertLessThan(60, count(DB::getQueryLog()));
    }
}
