<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Banner;
use App\Models\BannerPlacementDefinition;
use App\Models\Marketer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MarketerBannerAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function admin(array $perms = ['banners.view', 'banners.create', 'banners.edit']): Admin
    {
        Permission::firstOrCreate(['name' => 'vendors.assigned_only', 'guard_name' => 'admin']);
        foreach (array_merge($perms, ['banners.view', 'banners.create', 'banners.edit', 'banners.delete']) as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'admin']);
        }
        $admin = Admin::factory()->create();
        $admin->givePermissionTo($perms);

        return $admin;
    }

    private function payload(array $extra = []): array
    {
        return array_merge([
            'name' => 'Marketer banner',
            'placement_code' => 'marketer_profile_slider',
            'starts_at' => now()->subDay()->format('Y-m-d\TH:i'),
            'ends_at' => now()->addMonth()->format('Y-m-d\TH:i'),
            'status' => 'active',
            'device_target' => 'all',
            'audience' => 'all',
        ], $extra);
    }

    public function test_create_global_banner_with_gender_scope_and_default_men(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->postJson(route('admin.banners.store'), $this->payload([
            'placement_code' => 'marketers_list_hero', 'is_global' => 1, 'gender_scope' => 'female',
        ]))->assertOk()->assertJsonStructure(['message', 'redirect']);

        $banner = Banner::where('name', 'Marketer banner')->firstOrFail();
        $this->assertTrue($banner->is_global);
        $this->assertSame('female', $banner->gender_scope->value);
        $this->assertSame(0, $banner->marketers()->count());

        $this->actingAs($admin, 'admin')->postJson(route('admin.banners.store'), $this->payload([
            'name' => 'Default scope', 'placement_code' => 'marketers_list_strip', 'is_global' => 1,
        ]))->assertOk();
        $this->assertSame('male', Banner::where('name', 'Default scope')->first()->gender_scope->value);
    }

    public function test_create_banner_for_selected_marketers_ignores_gender_scope(): void
    {
        $marketers = Marketer::factory()->count(2)->create();

        $this->actingAs($this->admin(), 'admin')->postJson(route('admin.banners.store'), $this->payload([
            'is_global' => 0, 'gender_scope' => 'male', 'marketer_ids' => $marketers->pluck('id')->all(),
        ]))->assertOk();

        $banner = Banner::where('name', 'Marketer banner')->firstOrFail();
        $this->assertFalse($banner->is_global);
        $this->assertSame('all', $banner->gender_scope->value);
        $this->assertEqualsCanonicalizing($marketers->pluck('id')->all(), $banner->marketers()->pluck('marketers.id')->all());
    }

    public function test_validation_requires_existing_marketers_when_not_global(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->postJson(route('admin.banners.store'), $this->payload())
            ->assertStatus(422)->assertJsonValidationErrors('marketer_ids');

        $this->actingAs($admin, 'admin')->postJson(route('admin.banners.store'), $this->payload([
            'marketer_ids' => ['00000000-0000-0000-0000-000000000000'],
        ]))->assertStatus(422)->assertJsonValidationErrors('marketer_ids.0');

        $this->actingAs($admin, 'admin')->postJson(route('admin.banners.store'), $this->payload([
            'is_global' => 1, 'gender_scope' => 'bogus',
        ]))->assertStatus(422)->assertJsonValidationErrors('gender_scope');

        $this->assertSame(0, Banner::count());
    }

    public function test_marketers_are_ignored_for_other_placements(): void
    {
        $marketer = Marketer::factory()->create();
        BannerPlacementDefinition::where('code', 'marketers_list_hero')->firstOrFail()->replicate()->fill(['code' => 'homepage_hero'])->save();

        $this->actingAs($this->admin(), 'admin')->postJson(route('admin.banners.store'), $this->payload([
            'placement_code' => 'homepage_hero', 'is_global' => 1, 'gender_scope' => 'female', 'marketer_ids' => [$marketer->id],
        ]))->assertOk();

        $banner = Banner::where('name', 'Marketer banner')->firstOrFail();
        $this->assertFalse($banner->is_global);
        $this->assertSame('all', $banner->gender_scope->value);
        $this->assertSame(0, $banner->marketers()->count());
    }

    public function test_update_switches_scope_and_resyncs_pivot(): void
    {
        $admin = $this->admin();
        [$a, $b, $c] = Marketer::factory()->count(3)->create()->all();
        $banner = Banner::factory()->forMarketers([$a, $b])->create(['name' => 'Marketer banner']);

        // selected -> different selection
        $this->actingAs($admin, 'admin')->putJson(route('admin.banners.update', $banner), $this->payload([
            'marketer_ids' => [$b->id, $c->id],
        ]))->assertOk();
        $this->assertEqualsCanonicalizing([$b->id, $c->id], $banner->marketers()->pluck('marketers.id')->all());

        // selected -> global (pivot cleared)
        $this->actingAs($admin, 'admin')->putJson(route('admin.banners.update', $banner), $this->payload([
            'is_global' => 1, 'gender_scope' => 'male',
        ]))->assertOk();
        $banner->refresh();
        $this->assertTrue($banner->is_global);
        $this->assertSame('male', $banner->gender_scope->value);
        $this->assertSame(0, $banner->marketers()->count());

        // global -> selected
        $this->actingAs($admin, 'admin')->putJson(route('admin.banners.update', $banner), $this->payload([
            'is_global' => 0, 'marketer_ids' => [$a->id],
        ]))->assertOk();
        $banner->refresh();
        $this->assertFalse($banner->is_global);
        $this->assertSame('all', $banner->gender_scope->value);
        $this->assertSame([$a->id], $banner->marketers()->pluck('marketers.id')->all());
    }

    public function test_edit_page_and_index_render_with_targeting(): void
    {
        $admin = $this->admin();
        $marketer = Marketer::factory()->create(['name' => 'Layla Star']);
        $banner = Banner::factory()->forMarketers($marketer)->create(['name' => 'Sel']);
        Banner::factory()->marketerGlobal('marketers_list_hero', 'female')->create(['name' => 'Glob']);

        $this->actingAs($admin, 'admin')->get(route('admin.banners.edit', $banner))
            ->assertOk()->assertSee('Layla Star')->assertSee('marketers-search', false);
        $this->actingAs($admin, 'admin')->get(route('admin.banners.create'))
            ->assertOk()->assertSee('marketers_list_hero', false);

        $rows = $this->actingAs($admin, 'admin')->postJson(route('admin.banners.datatable'), ['draw' => 1, 'start' => 0, 'length' => 25])
            ->assertOk()->json('data');
        $html = json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->assertStringContainsString(e(__('admin.banners.target_n_marketers', ['count' => 1])), $html);
        $this->assertStringContainsString(e(__('admin.banners.target_global').' ('.__('admin.banners.gender_scope_female').')'), $html);
    }

    public function test_marketers_search_by_name_phone_email_and_permission(): void
    {
        $admin = $this->admin(['banners.view']);
        Marketer::factory()->create(['name' => 'Zed Alpha', 'phone' => '+201000000111', 'email' => 'zed@example.test']);
        Marketer::factory()->create(['name' => 'Other', 'phone' => '+201999999999', 'email' => 'other@example.test']);

        foreach (['Zed', '0000001', 'zed@example'] as $term) {
            $this->actingAs($admin, 'admin')->getJson(route('admin.banners.marketers-search', ['q' => $term]))
                ->assertOk()->assertJsonCount(1, 'results')->assertJsonPath('results.0.text', fn ($t) => str_contains($t, 'Zed Alpha'));
        }

        $noPerm = $this->admin(['dashboard.view']);
        $this->actingAs($noPerm, 'admin')->getJson(route('admin.banners.marketers-search'))->assertForbidden();
    }

    public function test_store_update_and_duplicate_are_permission_gated(): void
    {
        $viewer = $this->admin(['banners.view']);
        $marketer = Marketer::factory()->create();
        $banner = Banner::factory()->forMarketers($marketer)->create();

        $this->actingAs($viewer, 'admin')->postJson(route('admin.banners.store'), $this->payload(['is_global' => 1]))->assertForbidden();
        $this->actingAs($viewer, 'admin')->putJson(route('admin.banners.update', $banner), $this->payload(['is_global' => 1]))->assertForbidden();
        $this->actingAs($viewer, 'admin')->postJson(route('admin.banners.duplicate', $banner))->assertForbidden();
    }

    public function test_duplicate_keeps_marketer_targeting(): void
    {
        $admin = $this->admin();
        $marketers = Marketer::factory()->count(2)->create();
        $selected = Banner::factory()->forMarketers($marketers->all())->create();
        $global = Banner::factory()->marketerGlobal('marketers_list_strip', 'female')->create();

        $this->actingAs($admin, 'admin')->postJson(route('admin.banners.duplicate', $selected))->assertOk();
        $this->actingAs($admin, 'admin')->postJson(route('admin.banners.duplicate', $global))->assertOk();

        $copySelected = Banner::where('name', 'Copy of '.$selected->name)->firstOrFail();
        $this->assertFalse($copySelected->is_global);
        $this->assertEqualsCanonicalizing($marketers->pluck('id')->all(), $copySelected->marketers()->pluck('marketers.id')->all());

        $copyGlobal = Banner::where('name', 'Copy of '.$global->name)->firstOrFail();
        $this->assertTrue($copyGlobal->is_global);
        $this->assertSame('female', $copyGlobal->gender_scope->value);
    }
}
