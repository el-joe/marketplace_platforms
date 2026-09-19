<?php

namespace Tests\Feature\CustomPages;

use App\Models\Admin;
use App\Models\Category;
use App\Models\CustomPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CustomPageAdminFormTest extends TestCase
{
    use RefreshDatabase;

    private function admin(bool $perm = true): Admin
    {
        foreach (['categories.view', 'vendors.assigned_only'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'admin']);
        }
        $a = Admin::factory()->create();
        if ($perm) {
            $a->givePermissionTo('categories.view');
        }
        return $a;
    }

    private function cat(string $n = 'Cat'): Category
    {
        $c = new Category(['id' => (string) Str::uuid(), 'slug' => Str::slug($n) . '-' . uniqid(), 'name_en' => $n, 'name_ar' => $n, 'is_active' => true]);
        $c->save();
        return $c;
    }

    private function payload(array $o = []): array
    {
        return $o + ['name_en' => 'Deals', 'name_ar' => 'عروض'];
    }

    public function test_create_with_types_and_categories(): void
    {
        $c = $this->cat();
        $this->actingAs($this->admin(), 'admin')
            ->postJson(route('admin.custom-pages.store'), $this->payload(['listing_types' => ['vendor', 'admin', 'vendor'], 'category_ids' => [$c->id]]))
            ->assertOk();
        $p = CustomPage::first();
        $this->assertSame(['vendor', 'admin'], $p->listing_types);
        $this->assertFalse($p->all_categories);
        $this->assertSame([$c->id], $p->categories()->pluck('categories.id')->all());
    }

    public function test_create_all_categories_clears_picker_and_all_types_is_null(): void
    {
        $c = $this->cat();
        $this->actingAs($this->admin(), 'admin')
            ->postJson(route('admin.custom-pages.store'), $this->payload(['all_categories' => 1, 'category_ids' => [$c->id], 'listing_types' => ['admin', 'vendor', 'marketer']]))
            ->assertOk();
        $p = CustomPage::first();
        $this->assertTrue($p->all_categories);
        $this->assertNull($p->listing_types);
        $this->assertSame(0, $p->categories()->count());
    }

    public function test_update_switches_scope(): void
    {
        $c = $this->cat();
        $a = $this->admin();
        $this->actingAs($a, 'admin')->postJson(route('admin.custom-pages.store'), $this->payload(['category_ids' => [$c->id]]))->assertOk();
        $p = CustomPage::first();
        $this->actingAs($a, 'admin')->putJson(route('admin.custom-pages.update', $p->id), $this->payload(['all_categories' => 1, 'listing_types' => ['marketer']]))->assertOk();
        $p->refresh();
        $this->assertTrue($p->all_categories);
        $this->assertSame(['marketer'], $p->listing_types);
        $this->assertSame(0, $p->categories()->count());
        $this->actingAs($a, 'admin')->putJson(route('admin.custom-pages.update', $p->id), $this->payload(['category_ids' => [$c->id]]))->assertOk();
        $p->refresh();
        $this->assertFalse($p->all_categories);
        $this->assertNull($p->listing_types);
        $this->assertSame(1, $p->categories()->count());
    }

    public function test_validation_errors(): void
    {
        $a = $this->admin();
        $this->actingAs($a, 'admin')->postJson(route('admin.custom-pages.store'), $this->payload())
            ->assertStatus(422)->assertJsonValidationErrors('category_ids');
        $this->actingAs($a, 'admin')->postJson(route('admin.custom-pages.store'), $this->payload(['all_categories' => 1, 'listing_types' => ['bogus']]))
            ->assertStatus(422)->assertJsonValidationErrors('listing_types.0');
        $this->assertSame(0, CustomPage::count());
    }

    public function test_forbidden_without_permission(): void
    {
        $a = $this->admin(false);
        $this->actingAs($a, 'admin')->postJson(route('admin.custom-pages.store'), $this->payload(['all_categories' => 1]))->assertStatus(403);
        $this->actingAs($a, 'admin')->get(route('admin.custom-pages.create'))->assertStatus(403);
    }

    public function test_create_form_renders_controls_and_edit_prefills(): void
    {
        $a = $this->admin();
        $this->actingAs($a, 'admin')->get(route('admin.custom-pages.create'))->assertOk()
            ->assertSee('name="listing_types[]"', false)->assertSee('name="all_categories"', false);
        $p = CustomPage::create(['name_en' => 'X', 'name_ar' => 'س', 'listing_types' => ['vendor'], 'all_categories' => true]);
        $html = $this->actingAs($a, 'admin')->get(route('admin.custom-pages.edit', $p->id))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<option value="vendor"\s+selected/', $html);
        $this->assertDoesNotMatchRegularExpression('/<option value="admin"\s+selected/', $html);
        $this->assertMatchesRegularExpression('/name="all_categories"[^>]*checked/s', $html);
    }

    public function test_index_badges_and_xss_escaped(): void
    {
        CustomPage::create(['name_en' => '<script>alert(1)</script>', 'name_ar' => 'س', 'listing_types' => ['admin'], 'all_categories' => true]);
        $html = $this->actingAs($this->admin(), 'admin')->get(route('admin.custom-pages.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString(__('admin.custom_pages.type_admin'), $html);
        $this->assertStringContainsString(__('admin.custom_pages.all_categories'), $html);
    }

    public function test_old_input_preserved_on_form_redisplay(): void
    {
        $a = $this->admin();
        $html = $this->actingAs($a, 'admin')->withSession([])->withSession(['_old_input' => ['listing_types' => ['marketer'], 'all_categories' => '1']])
            ->get(route('admin.custom-pages.create'))->getContent();
        $this->assertMatchesRegularExpression('/<option value="marketer"\s+selected/', $html);
        $this->assertMatchesRegularExpression('/name="all_categories"[^>]*checked/s', $html);
    }
}
