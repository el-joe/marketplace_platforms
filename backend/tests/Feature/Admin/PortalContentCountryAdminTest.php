<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Country;
use App\Models\PortalContent;
use App\Services\PortalContentCountrySync;
use Database\Seeders\PortalContentSeeder;
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

        config(['portal_bands' => []]);

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

            $zeta = strpos($html, 'id="band-zeta"');
            $alpha = strpos($html, 'id="band-alpha"');
            $omega = strpos($html, 'id="band-omega"');
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

    private function bandsFixture(): void
    {
        foreach ([['zz_other', 'extra', 'title'], ['zz_secret', 'hidden', 'title']] as [$p, $b, $f]) {
            PortalContent::create(['page_key' => $p, 'block_key' => $b, 'field_key' => $f, 'type' => 'text', 'value_en' => "{$b} en", 'value_ar' => "{$b} ar"]);
        }
        config(['portal_bands' => ['zz_test' => [
            'label_en' => 'ZZ', 'label_ar' => 'زز', 'route' => '/zz', 'scope' => 'page',
            'bands' => [
                ['key' => '00-first', 'label_en' => 'First band', 'label_ar' => 'النطاق الأول', 'image' => null, 'blocks' => [['zz_test', 'zeta'], ['zz_other', 'extra']]],
                ['key' => '01-second', 'label_en' => 'Second band', 'label_ar' => 'النطاق الثاني', 'image' => 'zz_test/01-second', 'blocks' => [['zz_test', 'alpha']]],
            ],
        ]]]);
    }

    public function test_bands_render_in_declared_order_with_cross_key_block_and_chips(): void
    {
        $this->bandsFixture();
        $html = $this->actingAs($this->admin(), 'admin')->withSession(['locale' => 'en'])
            ->get(route('admin.portal-content.page', ['kw', 'zz_test']))->assertOk()->getContent();

        $p1 = strpos($html, 'id="band-00-first"');
        $p2 = strpos($html, 'id="band-01-second"');
        $this->assertTrue($p1 !== false && $p1 < $p2);
        $this->assertStringContainsString('href="#band-01-second"', $html);
        $this->assertStringContainsString('data-block="zz_other.extra"', $html);
        $this->assertStringNotContainsString('zz_secret.hidden', $html);
        $this->assertStringNotContainsString('data-block="zz_test.omega"', $html);
        $this->assertStringContainsString('_placeholder.en.svg', $html);
        $this->assertSame(1, PortalContent::where('country_code', 'kw')->where('page_key', 'zz_other')->count());
    }

    public function test_cross_key_block_is_saved_and_undeclared_pair_is_ignored(): void
    {
        $this->bandsFixture();
        app(PortalContentCountrySync::class)->copyGlobalToAllCountries();
        $admin = $this->admin();
        $cross = PortalContent::where('country_code', 'kw')->where('page_key', 'zz_other')->firstOrFail();
        $crossEg = PortalContent::where('country_code', 'eg')->where('page_key', 'zz_other')->firstOrFail();
        $secret = PortalContent::where('country_code', 'kw')->where('page_key', 'zz_secret')->firstOrFail();
        $omega = PortalContent::where('country_code', 'kw')->where('block_key', 'omega')->firstOrFail();

        $f = fn ($v) => ['value_en' => $v, 'value_ar' => $v, 'is_active' => '1'];
        $this->actingAs($admin, 'admin')->post(route('admin.portal-content.save', ['kw', 'zz_test']), ['fields' => [
            $cross->id => $f('cross saved'), $crossEg->id => $f('HACK'), $secret->id => $f('HACK'), $omega->id => $f('HACK'),
        ]])->assertRedirect();

        $this->assertSame('cross saved', $cross->fresh()->value_en);
        $this->assertSame($admin->id, $cross->fresh()->updated_by_admin_id);
        $this->assertSame('extra en', $crossEg->fresh()->value_en);
        $this->assertSame('hidden en', $secret->fresh()->value_en);
        $this->assertSame('omega en', $omega->fresh()->value_en);
        $this->assertSame('cross saved', PortalContent::forPage('zz_other', 'kw')->get('extra.title')->value_en);
    }

    public function test_direction_follows_admin_locale_and_uses_band_image_when_present(): void
    {
        $this->bandsFixture();
        $dir = public_path('images/portal-sections/zz_test');
        @mkdir($dir, 0777, true);
        file_put_contents($dir.'/01-second.en.png', 'x');

        try {
            $admin = $this->admin();
            $ltr = $this->actingAs($admin, 'admin')->withSession(['locale' => 'en'])->get(route('admin.portal-content.page', ['kw', 'zz_test']))->getContent();
            $this->assertStringContainsString('images/portal-sections/zz_test/01-second.en.png', $ltr);
            $this->assertStringContainsString('dir="ltr"', $ltr);

            // Arabic admin: no .ar file -> falls back to the English screenshot; first band has no image -> ar placeholder.
            $rtl = $this->actingAs($admin, 'admin')->withSession(['locale' => 'ar'])->get(route('admin.portal-content.page', ['kw', 'zz_test']))->getContent();
            $this->assertStringContainsString('dir="rtl"', $rtl);
            $this->assertStringContainsString('النطاق الأول', $rtl);
            $this->assertStringContainsString('01-second.en.png', $rtl);
            $this->assertStringContainsString('_placeholder.ar.svg', $rtl);
        } finally {
            @unlink($dir.'/01-second.en.png');
            @rmdir($dir);
        }
    }

    public function test_pages_without_bands_entry_fall_back_to_sections_config(): void
    {
        config(['portal_bands' => ['some_other_page' => ['bands' => []]]]);
        $html = $this->actingAs($this->admin(), 'admin')->withSession(['locale' => 'en'])
            ->get(route('admin.portal-content.page', ['kw', 'zz_test']))->assertOk()->getContent();

        $this->assertStringContainsString('id="band-zeta"', $html);
        $this->assertStringContainsString('Zeta first', $html);
        $this->assertStringContainsString('_placeholder.en.svg', $html);
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

    public function test_every_configured_page_renders_for_each_live_country_code_in_config_order(): void
    {
        foreach (['egy' => 'E1', 'kwt' => 'K1', 'omn' => 'O1', 'ksa' => 'S1', 'uae' => 'A1'] as $code => $iso) {
            Country::factory()->create(['site_code' => $code, 'iso_code_2' => $iso, 'is_active' => true]);
        }
        $this->seed(PortalContentSeeder::class);

        $admin = $this->admin();

        foreach (['egy', 'kwt', 'omn', 'ksa', 'uae'] as $code) {
            foreach (['join', 'marketers', 'marketer-type', 'footer', 'helpcenter'] as $page) {
                $this->actingAs($admin, 'admin')->withSession(['locale' => 'en'])
                    ->get(route('admin.portal-content.page', [$code, $page]))->assertOk();
            }
        }

        // Section rows follow config order (breadcrumb sits before the collection page).
        $html = $this->actingAs($admin, 'admin')->withSession(['locale' => 'en'])
            ->get(route('admin.portal-content.page', ['egy', 'marketers']))->getContent();
        $positions = array_map(fn ($label) => strpos($html, $label), ['>Hero<', '>Benefits<', '>Ways to earn<', '>Getting started steps<', '>Closing call-to-action<']);
        $this->assertNotContains(false, $positions);
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions);
    }
}
