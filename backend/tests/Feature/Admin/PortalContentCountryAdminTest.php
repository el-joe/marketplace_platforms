<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Country;
use App\Models\PortalContent;
use App\Services\PortalContentCountrySync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PortalContentCountryAdminTest extends TestCase
{
    use RefreshDatabase;

    private Country $kw;

    private Country $eg;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kw = Country::factory()->create(['site_code' => 'kw', 'iso_code_2' => 'KW', 'name_en' => 'Kuwait', 'is_active' => true]);
        $this->eg = Country::factory()->create(['site_code' => 'eg', 'iso_code_2' => 'EG', 'name_en' => 'Egypt', 'is_active' => true]);

        // Page "zz_test" with blocks deliberately in non-alphabetical config order.
        config()->set('portal_sections.zz_test', [
            'label_en' => 'ZZ Test Page', 'label_ar' => 'صفحة اختبار', 'route' => '/zz', 'scope' => 'page',
            'sections' => [
                ['block' => 'zeta', 'label_en' => 'Zeta first', 'label_ar' => 'زيتا', 'selector' => null],
                ['block' => 'alpha', 'label_en' => 'Alpha second', 'label_ar' => 'ألفا', 'selector' => null],
            ],
        ]);

        foreach ([['zeta', 'title'], ['alpha', 'title'], ['omega', 'title']] as [$block, $field]) {
            PortalContent::create([
                'page_key' => 'zz_test', 'block_key' => $block, 'field_key' => $field, 'type' => 'text',
                'value_en' => "{$block} en", 'value_ar' => "{$block} ar",
            ]);
        }
    }

    private function admin(array $perms = ['portal_content.view', 'portal_content.edit']): Admin
    {
        foreach (['vendors.assigned_only', 'portal_content.view', 'portal_content.edit'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'admin']);
        }
        $admin = Admin::factory()->create();
        $admin->givePermissionTo($perms);

        return $admin;
    }

    public function test_index_shows_flag_link_per_active_country(): void
    {
        Country::factory()->create(['site_code' => 'sa', 'iso_code_2' => 'SA', 'name_en' => 'Saudi', 'is_active' => false]);

        $response = $this->actingAs($this->admin(), 'admin')->withSession(['locale' => 'en'])->get(route('admin.portal-content.index'))->assertOk();

        $response->assertSee(route('admin.portal-content.page', ['kw', 'zz_test']), false)
            ->assertSee(route('admin.portal-content.page', ['eg', 'zz_test']), false)
            ->assertDontSee(route('admin.portal-content.page', ['sa', 'zz_test']), false)
            ->assertSee('https://f.nooncdn.com/s/app/com/common/images/flags/kw.svg', false)
            ->assertSee('ZZ Test Page', false);
    }

    public function test_page_renders_for_each_country_with_sections_in_config_order(): void
    {
        $admin = $this->admin();

        foreach (['kw', 'eg'] as $code) {
            $html = $this->actingAs($admin, 'admin')->withSession(['locale' => 'en'])->get(route('admin.portal-content.page', [$code, 'zz_test']))
                ->assertOk()->getContent();

            $zeta = strpos($html, 'id="section-zeta"');
            $alpha = strpos($html, 'id="section-alpha"');
            $omega = strpos($html, 'id="section-omega"');
            $this->assertTrue($zeta !== false && $zeta < $alpha && $alpha < $omega, 'sections must follow config order, unknown blocks last');
            $this->assertStringContainsString('_placeholder.en.svg', $html);
        }

        $this->assertSame(3, PortalContent::where('country_code', 'kw')->where('page_key', 'zz_test')->count());
    }

    public function test_arabic_admin_locale_uses_arabic_labels_and_rtl_placeholder(): void
    {
        $html = $this->actingAs($this->admin(), 'admin')->withSession(['locale' => 'ar'])
            ->get(route('admin.portal-content.page', ['kw', 'zz_test']))->assertOk()->getContent();

        $this->assertStringContainsString('dir="rtl"', $html);
        $this->assertStringContainsString('صفحة اختبار', $html);
        $this->assertStringContainsString('_placeholder.ar.svg', $html);
    }

    public function test_unknown_country_or_page_is_404(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'admin')->get(route('admin.portal-content.page', ['xx', 'zz_test']))->assertNotFound();
        $this->actingAs($admin, 'admin')->get(route('admin.portal-content.page', ['kw', 'nope']))->assertNotFound();
    }

    public function test_save_updates_only_the_selected_country(): void
    {
        app(PortalContentCountrySync::class)->copyGlobalToAllCountries();
        $admin = $this->admin();
        $kwRow = PortalContent::where('country_code', 'kw')->where('block_key', 'zeta')->first();
        $egRow = PortalContent::where('country_code', 'eg')->where('block_key', 'zeta')->first();
        $globalRow = PortalContent::where('country_code', '*')->where('block_key', 'zeta')->first();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.portal-content.save', ['kw', 'zz_test']), ['fields' => [
                $kwRow->id => ['value_en' => 'Kuwait only', 'value_ar' => 'الكويت فقط', 'is_active' => '1'],
                $egRow->id => ['value_en' => 'HACK', 'value_ar' => 'HACK', 'is_active' => '1'],
            ]])
            ->assertRedirect(route('admin.portal-content.page', ['kw', 'zz_test']));

        $this->assertSame('Kuwait only', $kwRow->fresh()->value_en);
        $this->assertSame($admin->id, $kwRow->fresh()->updated_by_admin_id);
        $this->assertSame('zeta en', $egRow->fresh()->value_en);
        $this->assertSame('zeta en', $globalRow->fresh()->value_en);

        $this->assertSame('Kuwait only', PortalContent::forPage('zz_test', 'kw')->get('zeta.title')->value_en);
        $this->assertSame('zeta en', PortalContent::forPage('zz_test', 'eg')->get('zeta.title')->value_en);
    }

    public function test_permissions_are_enforced(): void
    {
        $viewer = $this->admin(['portal_content.view']);
        $nobody = $this->admin([]);

        $this->actingAs($nobody, 'admin')->get(route('admin.portal-content.index'))->assertForbidden();
        $this->actingAs($nobody, 'admin')->get(route('admin.portal-content.page', ['kw', 'zz_test']))->assertForbidden();
        $this->actingAs($viewer, 'admin')->get(route('admin.portal-content.page', ['kw', 'zz_test']))->assertOk();
        $this->actingAs($viewer, 'admin')->post(route('admin.portal-content.save', ['kw', 'zz_test']), ['fields' => []])->assertForbidden();
    }

    public function test_legacy_urls_redirect_to_first_active_country(): void
    {
        $admin = $this->admin();

        // Alphabetical by English name: Egypt first.
        $this->actingAs($admin, 'admin')->get(route('admin.portal-content.legacy.page', 'zz_test'))
            ->assertRedirect(route('admin.portal-content.page', ['eg', 'zz_test']));

        $egRow = null;
        app(PortalContentCountrySync::class)->ensureCountryRows($this->eg, 'zz_test');
        $egRow = PortalContent::where('country_code', 'eg')->where('block_key', 'zeta')->first();

        $this->actingAs($admin, 'admin')->post(route('admin.portal-content.legacy.save', 'zz_test'), ['fields' => [
            $egRow->id => ['value_en' => 'Legacy save', 'value_ar' => 'x', 'is_active' => '1'],
        ]])->assertRedirect();

        $this->assertSame('Legacy save', $egRow->fresh()->value_en);
    }
}
