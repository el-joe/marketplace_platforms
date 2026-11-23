<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Faq;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Client note #26: search and filters on admin lists, plus the sidebar menu search.
 */
class AdminListSearchTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['vendors.assigned_only', 'faqs.view'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'admin']);
        }

        $this->admin = Admin::factory()->create();
        $this->admin->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'admin']));
        $this->admin->givePermissionTo('faqs.view');
    }

    public function test_faq_list_search_matches_arabic_text_and_status_filter_applies(): void
    {
        $match = Faq::create([
            'context' => 'seller', 'question_en' => 'How do refunds work '.Str::random(5), 'question_ar' => 'كيف يعمل الاسترداد',
            'answer_en' => 'Answer', 'answer_ar' => 'إجابة', 'sort_order' => 1, 'is_active' => true,
        ]);
        $other = Faq::create([
            'context' => 'seller', 'question_en' => 'Shipping zones '.Str::random(5), 'question_ar' => 'مناطق الشحن',
            'answer_en' => 'Answer', 'answer_ar' => 'إجابة', 'sort_order' => 2, 'is_active' => true,
        ]);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.faqs.index', ['context' => 'seller', 'search' => 'الاسترداد']))
            ->assertOk()
            ->assertSee($match->question_en)
            ->assertDontSee($other->question_en);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.faqs.index', ['context' => 'seller', 'status' => 'inactive']))
            ->assertOk()
            ->assertDontSee($match->question_en);
    }

    public function test_fbn_datatables_accept_a_global_search_term(): void
    {
        $payload = ['draw' => 1, 'start' => 0, 'length' => 10, 'search' => ['value' => 'anything'], 'order' => [['column' => 2, 'dir' => 'desc']]];

        foreach (['admin.fbn.inbound.datatable', 'admin.fbn.storage-fees.datatable', 'admin.fbn.marketplace.datatable'] as $route) {
            $this->actingAs($this->admin, 'admin')
                ->postJson(route($route), $payload)
                ->assertOk()
                ->assertJsonPath('recordsFiltered', 0);
        }
    }

    public function test_sidebar_menu_items_are_searchable_in_both_languages(): void
    {
        $html = $this->withSession(['locale' => 'ar'])
            ->actingAs($this->admin, 'admin')
            ->get(route('admin.faqs.index'))
            ->assertOk()
            ->assertSee('id="sidebar-search"', false)
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/data-nav-search="[^"]*'.preg_quote(trans('admin.nav.products', [], 'ar'), '/').'[^"]*'.preg_quote(trans('admin.nav.products', [], 'en'), '/').'/u',
            $html,
        );
    }
}
