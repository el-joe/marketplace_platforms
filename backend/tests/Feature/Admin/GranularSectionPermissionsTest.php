<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Client test report, point 19: blog, help center, ad support, live streams, cities,
 * currencies, newsletter and footer settings each have their own permission now.
 */
class GranularSectionPermissionsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function sections(): array
    {
        return [
            'blog posts' => ['admin.blog.posts.index', 'blog.view', 'pages.view'],
            'blog categories' => ['admin.blog.categories.index', 'blog.view', 'pages.view'],
            'help center articles' => ['admin.helpcenter.articles.index', 'help_center.view', 'pages.view'],
            'ad support articles' => ['admin.adsupport.articles.index', 'adsupport.view', 'pages.view'],
            'live streams' => ['admin.live-streams.index', 'live_streams.view', 'pages.view'],
            'cities' => ['admin.cities.index', 'cities.view', 'countries.view'],
            'currencies' => ['admin.currencies.index', 'currencies.view', 'countries.view'],
            'newsletter' => ['admin.newsletter.index', 'newsletter.view', 'settings.view'],
            'footer settings' => ['admin.footer-settings.index', 'footer_settings.manage', 'settings.content'],
        ];
    }

    private function adminWith(string $permission): Admin
    {
        Permission::findOrCreate('vendors.assigned_only', 'admin');
        Permission::findOrCreate($permission, 'admin');
        $admin = Admin::factory()->create();
        $admin->givePermissionTo($permission);

        return $admin;
    }

    #[DataProvider('sections')]
    public function test_the_section_permission_alone_opens_the_screen(string $route, string $permission, string $umbrella): void
    {
        $this->actingAs($this->adminWith($permission), 'admin')
            ->get(route($route))
            ->assertOk();
    }

    #[DataProvider('sections')]
    public function test_the_old_umbrella_permission_no_longer_opens_the_screen(string $route, string $permission, string $umbrella): void
    {
        $this->actingAs($this->adminWith($umbrella), 'admin')
            ->get(route($route))
            ->assertForbidden();
    }

    public function test_migration_grants_new_permissions_to_roles_holding_the_old_one(): void
    {
        $contentRole = Role::findOrCreate('content_editor', 'admin');
        $contentRole->givePermissionTo([Permission::findOrCreate('pages.view', 'admin'), Permission::findOrCreate('pages.manage', 'admin')]);
        $financeRole = Role::findOrCreate('finance_admin', 'admin');
        $financeRole->givePermissionTo(Permission::findOrCreate('payouts.view', 'admin'));

        $files = glob(database_path('migrations/*_add_granular_content_and_settings_permissions.php'));
        (require $files[0])->up();

        $this->assertTrue($contentRole->fresh()->hasPermissionTo('blog.view', 'admin'));
        $this->assertTrue($contentRole->fresh()->hasPermissionTo('help_center.manage', 'admin'));
        $this->assertFalse($contentRole->fresh()->hasPermissionTo('cities.view', 'admin'));
        $this->assertFalse($financeRole->fresh()->hasPermissionTo('blog.view', 'admin'));
    }
}
