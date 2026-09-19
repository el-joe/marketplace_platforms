<?php

namespace Tests\Feature\CustomPages;

use App\Models\Category;
use App\Models\CustomPage;
use App\Services\CustomPageService;
use App\Services\Customer\CategoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomPageScopeTest extends TestCase
{
    use RefreshDatabase;

    private function page(array $a = []): CustomPage
    {
        return CustomPage::create($a + ['name_en' => 'P', 'name_ar' => 'ص']);
    }

    private function cat(string $n, ?Category $parent = null): Category
    {
        $c = new Category(['id' => (string) \Illuminate\Support\Str::uuid(), 'slug' => \Illuminate\Support\Str::slug($n).'-'.uniqid(), 'name_en' => $n, 'name_ar' => $n, 'is_active' => true, 'parent_id' => $parent?->id]);
        $c->save();
        return $c->fresh();
    }

    public function test_casts_and_defaults(): void
    {
        $p = $this->page(['listing_types' => ['admin']])->fresh();
        $this->assertSame(['admin'], $p->listing_types);
        $this->assertFalse($p->all_categories);
        $this->assertNull($this->page()->fresh()->listing_types);
        $this->assertTrue($this->page(['all_categories' => 1])->fresh()->all_categories);
    }

    public function test_allowed_types(): void
    {
        $this->assertSame(['admin', 'vendor', 'marketer'], $this->page()->allowedListingTypes());
        $this->assertSame(['admin', 'vendor', 'marketer'], $this->page(['listing_types' => []])->allowedListingTypes());
        $p = $this->page(['listing_types' => ['marketer', 'bogus']]);
        $this->assertSame(['marketer'], $p->allowedListingTypes());
        $this->assertTrue($p->allowsType('marketer'));
        $this->assertFalse($p->allowsType('admin'));
    }

    public function test_scope_resolution(): void
    {
        $svc = app(CategoryService::class);
        $root = $this->cat('Root');
        $child = $this->cat('Child', $root);
        $other = $this->cat('Other');

        $all = $this->page(['all_categories' => true]);
        $this->assertNull($svc->resolveCustomPageScope($all)['category_ids']);

        $sub = $this->page();
        app(CustomPageService::class)->syncCategories($sub, [$root->id]);
        $ids = $svc->resolveCustomPageScope($sub->fresh())['category_ids'];
        $this->assertEqualsCanonicalizing([$root->id, $child->id], $ids);
        $this->assertNotContains($other->id, $ids);

        $empty = $this->page();
        $this->assertSame([], $svc->resolveCustomPageScope($empty)['category_ids']);
        app(CustomPageService::class)->syncCategories($sub, []);
        $this->assertSame([], $svc->resolveCustomPageScope($sub->fresh())['category_ids']);
    }

    public function test_deleted_category_leaves_empty_scope(): void
    {
        $c = $this->cat('Gone');
        $p = $this->page();
        app(CustomPageService::class)->syncCategories($p, [$c->id]);
        $c->forceDelete();
        $this->assertSame([], app(CategoryService::class)->resolveCustomPageScope($p->fresh())['category_ids']);
    }

    public function test_filter_helpers_via_slug(): void
    {
        $svc = app(CategoryService::class);
        $p = $this->page(['all_categories' => true]);
        $p->slugRecord()->create(['slug_url' => 'all-p']);
        $this->assertNull($svc->getCategoryScopeForFilter('all-p'));
        $this->assertSame([], $svc->getCategoryIdsForFilter('all-p'));
    }

    public function test_normalize_listing_types(): void
    {
        $s = app(CustomPageService::class);
        $this->assertNull($s->normalizeListingTypes(null));
        $this->assertNull($s->normalizeListingTypes([]));
        $this->assertNull($s->normalizeListingTypes(['x']));
        $this->assertNull($s->normalizeListingTypes(['vendor', 'marketer', 'admin']));
        $this->assertSame(['vendor', 'admin'], $s->normalizeListingTypes(['vendor', 'x', 'admin', 'vendor']));
        $this->assertSame(['marketer'], $s->normalizeListingTypes(['marketer']));
    }
}
