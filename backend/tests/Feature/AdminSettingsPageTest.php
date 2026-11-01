<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Setting;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Admin /settings page: every tab renders and no dead settings are (re)introduced. */
class AdminSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    private const REMOVED_KEYS = [
        'max_login_attempts', 'require_2fa_admins', 'cart_expiry_days_guest', 'cod_shortfall_policy',
        'payment_gateway_provider', 'sms_gateway_api_key', 'notify_admin_new_order', 'vendor_commission_default',
        'loyalty_points_per_100_egp', 'meilisearch_host',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        app()->setLocale('en');
        $this->seed(SettingsSeeder::class);
    }

    private function admin(): Admin
    {
        foreach (['vendors.assigned_only', 'settings.view'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'admin']);
        }
        $admin = Admin::factory()->create();
        $admin->givePermissionTo('settings.view');

        return $admin;
    }

    public function test_seeder_does_not_define_removed_settings(): void
    {
        $this->assertSame(0, Setting::whereIn('key', self::REMOVED_KEYS)->count());
    }

    public function test_every_category_has_a_partial_and_renders(): void
    {
        $admin = $this->admin();
        $categories = Setting::query()->get()->pluck('category')->map->value->unique();

        $this->assertNotEmpty($categories);
        foreach ($categories as $category) {
            $this->actingAs($admin, 'admin')
                ->get(route('admin.settings.index', ['tab' => $category]))
                ->assertOk();
        }
    }

    public function test_test_gateway_stub_route_is_gone(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.settings.test-gateway'));
    }
}
