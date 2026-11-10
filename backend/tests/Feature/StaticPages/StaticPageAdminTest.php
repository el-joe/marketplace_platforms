<?php

namespace Tests\Feature\StaticPages;

use App\Models\Admin;
use App\Models\StaticPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class StaticPageAdminTest extends TestCase
{
    use RefreshDatabase;

    private const PERMISSIONS = ['static_pages.view', 'static_pages.create', 'static_pages.edit', 'static_pages.delete'];

    private function admin(array $permissions = self::PERMISSIONS): Admin
    {
        foreach (['vendors.assigned_only', ...self::PERMISSIONS] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'admin']);
        }

        $admin = Admin::factory()->create();
        $admin->givePermissionTo($permissions);

        return $admin;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title_en' => 'Cookie Policy',
            'title_ar' => 'سياسة ملفات تعريف الارتباط',
            'slug' => '',
            'content_en' => '<p>How we use cookies.</p>',
            'content_ar' => '<p>كيف نستخدم ملفات تعريف الارتباط.</p>',
            'seo_title_en' => 'Cookies | Nawy',
            'seo_description_en' => 'Our cookie policy.',
            'audience' => StaticPage::AUDIENCE_BOTH,
            'show_in_footer' => '1',
            'footer_group' => StaticPage::FOOTER_GROUP_POLICIES,
            'sort_order' => 4,
            'is_active' => '1',
        ], $overrides);
    }

    public function test_index_and_create_pages_render_for_permitted_admin(): void
    {
        StaticPage::factory()->create(['title_en' => 'About Us']);
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->get(route('admin.static-pages.index'))->assertOk();
        $this->actingAs($admin, 'admin')->get(route('admin.static-pages.create'))->assertOk();
    }

    public function test_datatable_lists_and_filters_pages(): void
    {
        StaticPage::factory()->portal()->create(['title_en' => 'Seller Terms']);
        StaticPage::factory()->storefront()->create(['title_en' => 'Shopper Terms']);

        $response = $this->actingAs($this->admin(), 'admin')
            ->postJson(route('admin.static-pages.datatable'), ['draw' => 1, 'start' => 0, 'length' => 25, 'audience' => 'portal'])
            ->assertOk();

        $this->assertSame(1, $response->json('recordsFiltered'));
        $this->assertStringContainsString('Seller Terms', $response->json('data.0.title'));
    }

    public function test_admin_without_view_permission_is_forbidden(): void
    {
        $this->actingAs($this->admin([]), 'admin')->get(route('admin.static-pages.index'))->assertForbidden();
    }

    public function test_admin_with_view_only_cannot_create(): void
    {
        $this->actingAs($this->admin(['static_pages.view']), 'admin')
            ->post(route('admin.static-pages.store'), $this->payload())
            ->assertForbidden();

        $this->assertDatabaseCount('static_pages', 0);
    }

    public function test_store_creates_page_with_slug_from_english_title(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'admin')->post(route('admin.static-pages.store'), $this->payload());

        $page = StaticPage::sole();
        $response->assertRedirect(route('admin.static-pages.edit', $page->id))->assertSessionHas('success');
        $this->assertSame('cookie-policy', $page->slug);
        $this->assertSame('سياسة ملفات تعريف الارتباط', $page->title_ar);
        $this->assertSame(StaticPage::FOOTER_GROUP_POLICIES, $page->footer_group);
        $this->assertTrue($page->show_in_footer);
        $this->assertTrue($page->is_active);
        $this->assertSame(4, $page->sort_order);
        $this->assertSame($admin->id, $page->updated_by_admin_id);
    }

    public function test_store_normalizes_a_given_slug(): void
    {
        $this->actingAs($this->admin(), 'admin')->post(route('admin.static-pages.store'), $this->payload(['slug' => 'Our Cookies!']));

        $this->assertSame('our-cookies', StaticPage::sole()->slug);
    }

    public function test_store_transliterates_a_non_latin_english_title_into_the_slug(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.static-pages.store'), $this->payload(['title_en' => 'من نحن', 'slug' => '']))
            ->assertSessionHasNoErrors();

        $this->assertMatchesRegularExpression('/^[a-z0-9]+(-[a-z0-9]+)*$/', StaticPage::sole()->slug);
    }

    public function test_store_requires_slug_when_english_title_gives_none(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.static-pages.store'), $this->payload(['title_en' => '★ ★ ★', 'slug' => '؟؟']))
            ->assertSessionHasErrors('slug');

        $this->assertDatabaseCount('static_pages', 0);
    }

    public function test_store_validates_required_fields_and_enums(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.static-pages.store'), $this->payload([
                'title_ar' => '',
                'audience' => 'everyone',
                'footer_group' => 'legal',
            ]))
            ->assertSessionHasErrors(['title_ar', 'audience', 'footer_group']);
    }

    public function test_footer_group_is_required_when_shown_in_footer(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.static-pages.store'), $this->payload(['show_in_footer' => '1', 'footer_group' => '']))
            ->assertSessionHasErrors('footer_group');

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.static-pages.store'), $this->payload(['show_in_footer' => '0', 'footer_group' => '']))
            ->assertSessionHasNoErrors();
    }

    public function test_slug_must_be_unique_including_soft_deleted_pages(): void
    {
        StaticPage::factory()->create(['slug' => 'cookie-policy'])->delete();

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.static-pages.store'), $this->payload())
            ->assertSessionHasErrors('slug');

        $this->assertSame(1, StaticPage::withTrashed()->count());
    }

    public function test_update_keeps_own_slug_but_rejects_another_pages_slug(): void
    {
        $page = StaticPage::factory()->create(['slug' => 'cookie-policy']);
        StaticPage::factory()->create(['slug' => 'privacy-policy']);
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->put(route('admin.static-pages.update', $page->id), $this->payload(['slug' => 'cookie-policy', 'title_en' => 'Cookies']))
            ->assertRedirect(route('admin.static-pages.edit', $page->id))
            ->assertSessionHasNoErrors();
        $this->assertSame('Cookies', $page->fresh()->title_en);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.static-pages.update', $page->id), $this->payload(['slug' => 'privacy-policy']))
            ->assertSessionHasErrors('slug');
        $this->assertSame('cookie-policy', $page->fresh()->slug);
    }

    public function test_update_unchecked_checkboxes_turn_flags_off(): void
    {
        $page = StaticPage::factory()->inFooter()->create();

        $this->actingAs($this->admin(), 'admin')
            ->put(route('admin.static-pages.update', $page->id), $this->payload([
                'slug' => $page->slug, 'is_active' => '0', 'show_in_footer' => '0', 'footer_group' => '',
            ]))
            ->assertSessionHasNoErrors();

        $page->refresh();
        $this->assertFalse($page->is_active);
        $this->assertFalse($page->show_in_footer);
        $this->assertNull($page->footer_group);
    }

    public function test_content_html_is_sanitized_on_save(): void
    {
        $this->actingAs($this->admin(), 'admin')->post(route('admin.static-pages.store'), $this->payload([
            'content_en' => '<h2 onclick="steal()">Intro</h2><script>alert(1)</script><p>Read <a href="javascript:alert(1)">this</a> and <a href="https://nawy.test/x" target="_blank">that</a>.</p><iframe src="https://evil.test"></iframe><img src="x" onerror="alert(1)">',
            'content_ar' => '<p style="text-align: right; background-image: url(x)">نص <b>عريض</b></p>',
        ]));

        $page = StaticPage::sole();
        $this->assertSame(
            '<h2>Intro</h2><p>Read <a>this</a> and <a href="https://nawy.test/x" target="_blank" rel="noopener noreferrer">that</a>.</p><img src="x">',
            $page->content_en,
        );
        $this->assertSame('<p style="text-align: right">نص <b>عريض</b></p>', $page->content_ar);
    }

    public function test_destroy_soft_deletes_the_page(): void
    {
        $page = StaticPage::factory()->create();

        $this->actingAs($this->admin(), 'admin')
            ->deleteJson(route('admin.static-pages.destroy', $page->id))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSoftDeleted($page);
    }

    public function test_delete_requires_delete_permission(): void
    {
        $page = StaticPage::factory()->create();

        $this->actingAs($this->admin(['static_pages.view', 'static_pages.edit']), 'admin')
            ->deleteJson(route('admin.static-pages.destroy', $page->id))
            ->assertForbidden();

        $this->assertNotSoftDeleted($page);
    }

    public function test_toggle_flips_active_flag(): void
    {
        $page = StaticPage::factory()->create(['is_active' => true]);

        $this->actingAs($this->admin(), 'admin')
            ->postJson(route('admin.static-pages.toggle', $page->id))
            ->assertOk()
            ->assertJson(['is_active' => false]);

        $this->assertFalse($page->fresh()->is_active);
    }
}
