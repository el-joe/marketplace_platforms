<?php

namespace Tests\Feature\Portal;

use App\Models\Admin;
use App\Models\Country;
use App\Models\PortalContent;
use App\Services\PortalContentCountrySync;
use Database\Seeders\Concerns\SyncsPortalContent;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PortalContentCountryTest extends TestCase
{
    use RefreshDatabase;

    private function country(string $code, bool $active = true): Country
    {
        return Country::factory()->create(['site_code' => $code, 'is_active' => $active, 'is_launched' => true]);
    }

    private function row(string $country, string $en, array $extra = [], string $page = 'zz_page', string $block = 'hero', string $field = 'title'): PortalContent
    {
        return PortalContent::create([
            'country_code' => $country, 'page_key' => $page, 'block_key' => $block, 'field_key' => $field,
            'type' => 'text', 'value_en' => $en, 'value_ar' => $en.' ar',
        ] + $extra);
    }

    private function zz(string $country)
    {
        return PortalContent::where('country_code', $country)->where('page_key', 'zz_page');
    }

    private function text(?string $country = null): ?string
    {
        return PortalContent::forPage('zz_page', $country)->get('hero.title')?->value_en;
    }

    public function test_new_rows_default_to_global_layer(): void
    {
        $row = PortalContent::create(['page_key' => 'p', 'block_key' => 'b', 'field_key' => 'f', 'type' => 'text']);

        $this->assertSame('*', $row->fresh()->country_code);
    }

    public function test_unique_key_is_per_country(): void
    {
        $this->row('*', 'g');
        $this->row('kwt', 'k'); // same page/block/field, other layer: allowed

        $this->expectException(QueryException::class);
        $this->row('kwt', 'dup');
    }

    public function test_resolution_order_explicit_route_session_default(): void
    {
        $this->country('kwt');
        $this->country('uae');
        $this->row('*', 'global');
        $this->row('kwt', 'kuwait');
        $this->row('uae', 'emirates');

        // explicit param
        $this->assertSame('emirates', $this->text('uae'));

        // session
        $this->withSession(['portal_country' => 'kwt']);
        $this->assertSame('kuwait', $this->text());

        // explicit beats session
        $this->assertSame('emirates', $this->text('uae'));

        // route segment beats session
        $request = request()->duplicate();
        $route = new Route('GET', '/{country}/x', []);
        $route->bind($request);
        $route->setParameter('country', 'uae');
        $request->setRouteResolver(fn () => $route);
        app()->instance('request', $request);
        $this->assertSame('emirates', $this->text());
    }

    public function test_default_country_is_first_launched_when_nothing_else_resolves(): void
    {
        $this->country('kwt');
        $this->row('*', 'global');
        $this->row('kwt', 'kuwait');

        $this->assertSame('kuwait', $this->text());
    }

    public function test_global_is_used_when_no_country_resolves(): void
    {
        $this->row('*', 'global');

        $this->assertSame('global', $this->text());
        $this->assertSame('global', $this->text('*'));
    }

    public function test_fallback_chain_country_then_global_then_missing(): void
    {
        $this->country('kwt');
        $this->row('*', 'global');
        $this->row('*', 'g2', [], 'zz_page', 'hero', 'sub');

        // no country row -> global
        $this->assertSame('global', $this->text('kwt'));

        // country row inactive -> global
        $kw = $this->row('kwt', 'kuwait', ['is_active' => false]);
        PortalContent::flush('zz_page');
        $this->assertSame('global', $this->text('kwt'));

        // active but empty -> global
        $kw->update(['is_active' => true, 'value_en' => '', 'value_ar' => '', 'value_url' => null]);
        PortalContent::flush('zz_page');
        $this->assertSame('global', $this->text('kwt'));

        // usable -> country
        $kw->update(['value_en' => 'kuwait']);
        PortalContent::flush('zz_page');
        $this->assertSame('kuwait', $this->text('kwt'));

        // nothing anywhere -> missing (Blade fallback)
        $this->assertNull(PortalContent::forPage('zz_page', 'kwt')->get('hero.missing'));
        $this->assertNull(PortalContent::forPage('no_such_page', 'kwt')->first());
    }

    public function test_helpers_use_country_content_with_blade_fallback(): void
    {
        $this->country('kwt');
        $this->row('kwt', 'kuwait', [], 'zz_page', 'hero', 'title');
        $this->withSession(['portal_country' => 'kwt', 'locale' => 'en']);

        $this->assertSame('kuwait', portal_content('zz_page', 'hero', 'title', 'fb'));
        $this->assertSame('fb', portal_content('zz_page', 'hero', 'nope', 'fb'));
    }

    public function test_flush_country_only_and_global_flushes_all(): void
    {
        $this->country('kwt');
        $this->country('uae');
        $this->row('*', 'global');
        $this->text('kwt');
        $this->text('uae');
        $this->text('*');

        // country flush: only that cache entry is dropped
        PortalContent::flush('zz_page', 'kwt');
        $this->assertFalse(Cache::has('portal_content:kwt:zz_page'));
        $this->assertTrue(Cache::has('portal_content:uae:zz_page'));

        // global flush: every country + the global layer
        $this->text('kwt');
        PortalContent::flush('zz_page');
        $this->assertFalse(Cache::has('portal_content:kwt:zz_page'));
        $this->assertFalse(Cache::has('portal_content:uae:zz_page'));
        $this->assertFalse(Cache::has('portal_content:*:zz_page'));
    }

    public function test_cache_serves_stale_until_flushed(): void
    {
        $this->country('kwt');
        $g = $this->row('*', 'old');
        $this->assertSame('old', $this->text('kwt'));

        $g->update(['value_en' => 'new']);
        $this->assertSame('old', $this->text('kwt'));

        PortalContent::flush('zz_page');
        $this->assertSame('new', $this->text('kwt'));
    }

    public function test_observer_copies_global_rows_to_new_active_country(): void
    {
        $admin = Admin::factory()->create();
        $this->row('*', 'g1', ['sort_order' => 7, 'is_active' => false, 'updated_by_admin_id' => $admin->id]);
        $this->row('*', 'g2', [], 'zz_page', 'hero', 'sub');

        $this->country('kwt', false);
        $this->assertSame(0, $this->zz('kwt')->count(), 'inactive country gets nothing yet');

        $active = $this->country('uae');
        $copied = $this->zz('uae')->orderBy('field_key')->get();
        $this->assertCount(2, $copied);
        $sub = $copied->firstWhere('field_key', 'title');
        $this->assertSame('g1', $sub->value_en);
        $this->assertSame(7, $sub->sort_order);
        $this->assertFalse($sub->is_active);
        $this->assertSame($admin->id, $sub->updated_by_admin_id);

        // activating the inactive country materialises it too
        Country::where('site_code', 'kwt')->first()->update(['is_active' => true]);
        $this->assertSame(2, $this->zz('kwt')->count());
    }

    public function test_copy_is_idempotent_and_never_overwrites_country_edits(): void
    {
        $this->row('*', 'g1');
        $this->row('*', 'g2', [], 'zz_page', 'hero', 'sub');
        $this->country('kwt');
        $this->zz('kwt')->where('field_key', 'title')->update(['value_en' => 'edited']);

        $sync = app(PortalContentCountrySync::class);
        $this->assertSame(0, $sync->copyGlobalToCountry('kwt'));
        $this->assertSame(0, $sync->copyGlobalToAllCountries());
        $this->assertSame(0, $sync->ensureCountryRows('kwt', 'zz_page'));

        $this->assertSame(2, $this->zz('kwt')->count());
        $this->assertSame('edited', $this->zz('kwt')->where('field_key', 'title')->value('value_en'));

        // a row deleted from the country is restored by ensureCountryRows, alone
        $this->zz('kwt')->where('field_key', 'sub')->delete();
        $this->assertSame(1, $sync->ensureCountryRows('kwt', 'zz_page'));
    }

    public function test_data_migration_copies_for_every_country_and_is_idempotent(): void
    {
        $this->row('*', 'g1');
        $this->country('kwt');
        $this->country('uae', false);
        $this->zz('kwt')->delete();

        $migration = require database_path('migrations/2026_10_24_100100_copy_portal_content_to_countries.php');
        $migration->up();
        $migration->up();

        $this->assertSame(1, $this->zz('kwt')->count());
        $this->assertSame(1, $this->zz('uae')->count());
        $this->assertSame(1, $this->zz('*')->count());
    }

    public function test_seeder_sync_fans_out_and_preserves_admin_edited_country_rows(): void
    {
        $admin = Admin::factory()->create();
        $this->country('kwt');
        $this->country('uae', false); // inactive: not refreshed by the seeder

        $syncer = new class
        {
            use SyncsPortalContent {
                syncPortalContent as public;
            }
        };

        $stats = $syncer->syncPortalContent([['zz_page', 'hero', 'title', 'text', 'v1', 'ع1', null, 1]]);
        $this->assertSame(1, $stats['created']);
        $this->assertSame('v1', $this->zz('kwt')->value('value_en'));
        $this->assertSame(0, $this->zz('uae')->count());

        // admin edits Kuwait; the canonical default changes
        $this->zz('kwt')->update(['value_en' => 'kw-edit', 'updated_by_admin_id' => $admin->id]);
        $this->country('egy');
        $syncer->syncPortalContent([['zz_page', 'hero', 'title', 'text', 'v2', 'ع2', null, 9]]);

        $kw = $this->zz('kwt')->first();
        $this->assertSame('kw-edit', $kw->value_en, 'admin edit preserved');
        $this->assertSame(9, $kw->sort_order, 'sort_order realigned');
        $this->assertSame('v2', $this->zz('egy')->value('value_en'));
        $this->assertSame('v2', $this->zz('*')->value('value_en'));
        $this->assertSame(1, $this->zz('*')->count());
        $this->assertSame('v2', $this->text('egy'));
    }

    public function test_portal_page_renders_country_specific_text(): void
    {
        PortalContent::where('country_code', '*')->where(['page_key' => 'faq', 'block_key' => 'meta', 'field_key' => 'title'])
            ->update(['value_en' => 'Global FAQ title', 'value_ar' => 'عنوان عام', 'is_active' => true]);
        $this->country('uae');
        $this->country('kwt');
        PortalContent::where('country_code', 'kwt')->where(['page_key' => 'faq', 'block_key' => 'meta', 'field_key' => 'title'])
            ->update(['value_ar' => 'عنوان الكويت', 'is_active' => true]);
        PortalContent::flush('faq');

        $this->withSession(['locale' => 'ar', 'portal_country' => 'kwt'])
            ->get(route('portal.faq'))->assertOk()->assertSee('عنوان الكويت')->assertDontSee('عنوان عام');

        $this->withSession(['locale' => 'ar', 'portal_country' => 'uae'])
            ->get(route('portal.faq'))->assertOk()->assertSee('عنوان عام')->assertDontSee('عنوان الكويت');
    }
}
