<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Country;
use App\Models\PortalContent;
use App\Models\Setting;
use App\Services\PortalContentCountrySync;
use App\Support\PortalPermissions;
use Database\Seeders\PermissionRoleSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PortalContentPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private PortalContent $text;

    private PortalContent $image;

    protected function setUp(): void
    {
        parent::setUp();

        config(['portal_bands' => []]);
        PortalPermissions::ensureExist();
        foreach (['settings.view', 'settings.edit', 'dashboard.view', 'admins.view', 'admins.edit', 'roles.view', 'vendors.assigned_only'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'admin']);
        }

        $kw = Country::factory()->create(['site_code' => 'kw', 'iso_code_2' => 'KW', 'name_en' => 'Kuwait', 'is_active' => true]);

        config()->set('portal_sections.zz_test', [
            'label_en' => 'ZZ Test Page', 'label_ar' => 'صفحة اختبار', 'route' => '/zz', 'scope' => 'page',
            'sections' => [['block' => 'hero', 'label_en' => 'Hero', 'label_ar' => 'البطل', 'selector' => null]],
        ]);

        PortalContent::create(['page_key' => 'zz_test', 'block_key' => 'hero', 'field_key' => 'title', 'type' => 'text', 'value_en' => 'Old EN', 'value_ar' => 'Old AR', 'is_active' => false]);
        PortalContent::create(['page_key' => 'zz_test', 'block_key' => 'hero', 'field_key' => 'photo', 'type' => 'image', 'value_en' => 'p', 'value_ar' => 'p', 'value_url' => null]);

        app(PortalContentCountrySync::class)->ensureCountryRows($kw, 'zz_test');
        $this->text = PortalContent::where('country_code', 'kw')->where('page_key', 'zz_test')->where('field_key', 'title')->firstOrFail();
        $this->text->update(['is_active' => false]);
        $this->image = PortalContent::where('country_code', 'kw')->where('page_key', 'zz_test')->where('field_key', 'photo')->firstOrFail();
    }

    /** @param list<string> $permissions */
    private function staff(array $permissions, bool $viaRole = true): Admin
    {
        $admin = Admin::factory()->create();

        if ($viaRole) {
            $role = Role::create(['name' => 'role_'.uniqid(), 'guard_name' => 'admin']);
            $role->givePermissionTo($permissions);
            $admin->assignRole($role);
        } else {
            $admin->givePermissionTo($permissions);
        }

        return $admin;
    }

    private function save(Admin $admin, array $fields, bool $json = false)
    {
        $url = route('admin.portal-content.save', ['kw', 'zz_test']);
        $payload = ['fields' => $fields];

        return $json
            ? $this->actingAs($admin, 'admin')->postJson($url, $payload)
            : $this->actingAs($admin, 'admin')->post($url, $payload);
    }

    private function textField(array $extra = []): array
    {
        return [$this->text->id => $extra + ['value_en' => 'New EN', 'value_ar' => 'New AR']];
    }

    public function test_view_only_employee_can_open_pages_but_cannot_save(): void
    {
        $admin = $this->staff([PortalPermissions::VIEW]);

        $this->actingAs($admin, 'admin')->get(route('admin.portal-content.index'))->assertOk();
        $page = $this->actingAs($admin, 'admin')->get(route('admin.portal-content.page', ['kw', 'zz_test']))->assertOk();
        $page->assertSee('data-readonly-notice', false)->assertDontSee(__('admin.portal_content.save_page'));

        $this->save($admin, $this->textField())->assertForbidden();
        $this->assertSame('Old EN', $this->text->fresh()->value_en);
    }

    public function test_edit_employee_can_save_text_but_not_publish(): void
    {
        $admin = $this->staff([PortalPermissions::VIEW, PortalPermissions::EDIT]);

        $this->save($admin, $this->textField())->assertRedirect();
        $this->assertSame('New EN', $this->text->fresh()->value_en);
        $this->assertFalse($this->text->fresh()->is_active, 'absent is_active must not change the state');

        // Trying to switch the field on without publish permission is refused and nothing is written.
        $this->save($admin, $this->textField(['value_en' => 'Sneaky', 'is_active' => '1']))->assertForbidden();
        $this->assertSame('New EN', $this->text->fresh()->value_en);
        $this->assertFalse($this->text->fresh()->is_active);

        $page = $this->actingAs($admin, 'admin')->get(route('admin.portal-content.page', ['kw', 'zz_test']))->assertOk();
        $page->assertSee(__('admin.portal_content.save_page'))->assertDontSee('data-readonly-notice', false);
    }

    public function test_publish_permission_toggles_active_state(): void
    {
        $admin = $this->staff([PortalPermissions::VIEW, PortalPermissions::EDIT, PortalPermissions::PUBLISH]);

        $this->save($admin, $this->textField(['is_active' => '1']))->assertRedirect();
        $this->assertTrue($this->text->fresh()->is_active);

        $this->save($admin, $this->textField())->assertRedirect();
        $this->assertFalse($this->text->fresh()->is_active);
    }

    public function test_employee_without_portal_permissions_gets_403_and_no_sidebar_item(): void
    {
        $admin = $this->staff(['dashboard.view']);

        $this->actingAs($admin, 'admin')->get(route('admin.portal-content.index'))->assertForbidden();
        $this->actingAs($admin, 'admin')->get(route('admin.portal-content.page', ['kw', 'zz_test']))->assertForbidden();
        $this->save($admin, $this->textField())->assertForbidden();

        $this->actingAs($admin, 'admin')->get(route('admin.dashboard'))->assertOk()
            ->assertDontSee(route('admin.portal-content.index'), false);
    }

    public function test_sidebar_shows_portal_content_once_for_authorised_staff(): void
    {
        $admin = $this->staff(['dashboard.view', PortalPermissions::VIEW]);

        $html = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'href="'.route('admin.portal-content.index').'"'));
    }

    public function test_image_create_and_delete_are_separate_permissions(): void
    {
        Storage::fake('public');
        $svg = fn () => UploadedFile::fake()->createWithContent('a.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');
        $editor = $this->staff([PortalPermissions::VIEW, PortalPermissions::EDIT]);

        // Adding a first image needs "create".
        $this->save($editor, [$this->image->id => ['value_en' => 'p', 'value_ar' => 'p', 'value_file' => $svg()]])->assertForbidden();
        $this->assertNull($this->image->fresh()->value_url);

        $creator = $this->staff([PortalPermissions::VIEW, PortalPermissions::EDIT, PortalPermissions::CREATE]);
        $this->save($creator, [$this->image->id => ['value_en' => 'p', 'value_ar' => 'p', 'value_file' => $svg()]])->assertRedirect();
        $this->assertNotNull($this->image->fresh()->value_url);

        // Replacing an existing image needs only "edit"; removing it needs "delete".
        $this->save($editor, [$this->image->id => ['value_en' => 'p', 'value_ar' => 'p', 'value_file' => $svg()]])->assertRedirect();
        $this->save($editor, [$this->image->id => ['value_en' => 'p', 'value_ar' => 'p', 'reset_image' => '1']])->assertForbidden();
        $this->assertNotNull($this->image->fresh()->value_url);

        $deleter = $this->staff([PortalPermissions::VIEW, PortalPermissions::EDIT, PortalPermissions::DELETE]);
        $this->save($deleter, [$this->image->id => ['value_en' => 'p', 'value_ar' => 'p', 'reset_image' => '1']])->assertRedirect();
        $this->assertNull($this->image->fresh()->value_url);
    }

    public function test_unauthorised_xhr_requests_receive_json_403(): void
    {
        $viewer = $this->staff([PortalPermissions::VIEW]);
        $this->save($viewer, $this->textField(), json: true)->assertForbidden()->assertJsonStructure(['message']);

        $none = $this->staff(['dashboard.view']);
        $this->actingAs($none, 'admin')->getJson(route('admin.portal-content.index'))->assertForbidden()->assertJsonStructure(['message']);
    }

    public function test_join_option_toggles_require_manage_permission(): void
    {
        Setting::updateOrCreate(['key' => 'join_option_marketer_enabled'], ['value' => true, 'category' => 'general', 'is_public' => false]);
        $url = route('admin.settings.save', 'general');
        $flip = ['settings' => ['join_option_marketer_enabled' => '0']];

        $settingsEditor = $this->staff(['settings.view', 'settings.edit']);
        $this->actingAs($settingsEditor, 'admin')->postJson($url, $flip)->assertForbidden();
        $this->assertTrue((bool) Setting::where('key', 'join_option_marketer_enabled')->first()->value);

        // Re-submitting the unchanged value is not a change.
        $this->actingAs($settingsEditor, 'admin')->postJson($url, ['settings' => ['join_option_marketer_enabled' => '1']])->assertOk();

        $manager = $this->staff(['settings.view', 'settings.edit', PortalPermissions::MANAGE]);
        $this->actingAs($manager, 'admin')->postJson($url, $flip)->assertOk();
        $this->assertFalse((bool) Setting::where('key', 'join_option_marketer_enabled')->first()->value);
    }

    public function test_permissions_can_be_granted_directly_to_one_employee(): void
    {
        $admin = $this->staff([PortalPermissions::VIEW], viaRole: false);
        $this->actingAs($admin, 'admin')->get(route('admin.portal-content.index'))->assertOk();
        $this->save($admin, $this->textField())->assertForbidden();

        $admin->givePermissionTo(PortalPermissions::EDIT);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->save($admin->fresh(), $this->textField())->assertRedirect();
    }

    public function test_staff_form_grants_and_revokes_direct_portal_permissions_without_touching_other_direct_grants(): void
    {
        $grantor = $this->staff(['admins.view', 'admins.edit']);
        $target = $this->staff([], viaRole: false);
        $role = Role::create(['name' => 'plain', 'guard_name' => 'admin']);
        $target->assignRole($role);
        $target->givePermissionTo('settings.view'); // unrelated direct grant must survive

        $payload = fn (array $extra) => $extra + [
            'name' => $target->name, 'email' => $target->email, 'status' => 'active', 'roles' => ['plain'],
            'portal_permissions_present' => '1',
        ];

        $this->actingAs($grantor, 'admin')
            ->putJson(route('admin.admins.update', $target->id), $payload(['portal_permissions' => [PortalPermissions::VIEW, PortalPermissions::EDIT]]))
            ->assertOk();
        $target = $target->fresh();
        $this->assertTrue($target->hasDirectPermission(PortalPermissions::EDIT));
        $this->assertTrue($target->hasDirectPermission('settings.view'));

        $this->actingAs($grantor, 'admin')
            ->putJson(route('admin.admins.update', $target->id), $payload(['portal_permissions' => [PortalPermissions::VIEW]]))
            ->assertOk();
        $target = $target->fresh();
        $this->assertFalse($target->hasDirectPermission(PortalPermissions::EDIT));
        $this->assertTrue($target->hasDirectPermission(PortalPermissions::VIEW));
        $this->assertTrue($target->hasDirectPermission('settings.view'));

        // Unknown / non-portal names are rejected by validation.
        $this->actingAs($grantor, 'admin')
            ->putJson(route('admin.admins.update', $target->id), $payload(['portal_permissions' => ['admins.delete']]))
            ->assertStatus(422);
    }

    public function test_staff_edit_page_lists_portal_permissions(): void
    {
        $grantor = $this->staff(['admins.view', 'admins.edit']);
        $target = $this->staff([PortalPermissions::VIEW]);

        $html = $this->actingAs($grantor, 'admin')->get(route('admin.admins.edit', $target->id))->assertOk()->getContent();

        $this->assertStringContainsString('data-portal-permissions', $html);
        foreach (PortalPermissions::CONTENT as $permission) {
            $this->assertStringContainsString('value="'.$permission.'"', $html);
        }
        $this->assertStringContainsString(PortalPermissions::label(PortalPermissions::PUBLISH), $html);
    }

    public function test_role_editor_exposes_translated_portal_group_in_both_locales(): void
    {
        $admin = $this->staff(['roles.view']);

        foreach (['en', 'ar'] as $locale) {
            app()->setLocale($locale);
            $group = collect($this->actingAs($admin, 'admin')->withSession(['locale' => $locale])->getJson(route('admin.roles.permissions'))
                ->assertOk()->json('data'))->firstWhere('key', 'portal_content');

            $this->assertNotNull($group);
            $this->assertSame(__('admin.portal_permissions.group', [], $locale), $group['label']);
            $this->assertNotSame('Portal_content', $group['label']);
            $this->assertSame(PortalPermissions::CONTENT, array_column($group['permissions'], 'name'));
            foreach ($group['permissions'] as $permission) {
                $this->assertNotEmpty($permission['label']);
                $this->assertNotEmpty($permission['description']);
                $this->assertStringNotContainsString('admin.portal_permissions', $permission['label'].$permission['description']);
                $this->assertSame(__('admin.portal_permissions.items.'.str_replace('.', '_', $permission['name']).'.label', [], $locale), $permission['label']);
            }
        }

        $this->assertNotSame(__('admin.portal_permissions.group', [], 'en'), __('admin.portal_permissions.group', [], 'ar'));
    }

    public function test_seeders_never_remove_permissions_added_to_system_roles(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $role = Role::where('name', 'operations_admin')->where('guard_name', 'admin')->firstOrFail();
        $this->assertFalse($role->hasPermissionTo(PortalPermissions::EDIT));
        $role->givePermissionTo([PortalPermissions::VIEW, PortalPermissions::EDIT]);
        $before = $role->permissions()->count();

        // What `seed:run --permissions-only` runs on every deploy.
        $restricted = Admin::factory()->create(['email' => 'restricted@example.test']);
        $restricted->assignRole($role);
        $this->seed(PermissionSeeder::class);
        $this->seed(PermissionRoleSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $role = $role->fresh();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->assertTrue($role->hasPermissionTo(PortalPermissions::EDIT));
        $this->assertTrue($role->hasPermissionTo(PortalPermissions::VIEW));
        $this->assertSame($before, $role->permissions()->count());

        // A removal made by an administrator is not undone either.
        $role->revokePermissionTo('orders.cancel');
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->assertFalse($role->fresh()->hasPermissionTo('orders.cancel'));

        // Restricted staff is not promoted; super_admin holds every portal permission.
        $this->assertFalse($restricted->fresh()->hasRole('super_admin'));
        $super = Role::where('name', 'super_admin')->where('guard_name', 'admin')->firstOrFail();
        foreach (PortalPermissions::ALL as $permission) {
            $this->assertTrue($super->hasPermissionTo($permission), $permission);
        }
    }

    public function test_grant_migration_keeps_existing_access_and_is_idempotent(): void
    {
        // Recreate the pre-migration world: only view/edit exist.
        Permission::whereIn('name', ['portal_content.create', 'portal_content.delete', 'portal_content.publish', 'portal_content.manage', 'portal_analytics.view'])->delete();

        $viewer = Role::create(['name' => 'old_viewer', 'guard_name' => 'admin']);
        $viewer->givePermissionTo('portal_content.view');
        $editor = Role::create(['name' => 'old_editor', 'guard_name' => 'admin']);
        $editor->givePermissionTo(['portal_content.view', 'portal_content.edit']);
        $settings = Role::create(['name' => 'old_settings', 'guard_name' => 'admin']);
        $settings->givePermissionTo('settings.edit');
        $direct = Admin::factory()->create();
        $direct->givePermissionTo('portal_content.edit');
        $super = Role::create(['name' => 'super_admin', 'guard_name' => 'admin']);

        $migration = require database_path('migrations/2026_11_04_150000_add_portal_content_granular_permissions.php');
        $migration->up();
        $migration->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PortalPermissions::ALL as $permission) {
            $this->assertSame(1, Permission::where('name', $permission)->where('guard_name', 'admin')->count(), $permission);
            $this->assertTrue($super->fresh()->hasPermissionTo($permission), "super_admin {$permission}");
        }

        $this->assertEqualsCanonicalizing(['portal_content.view'], $viewer->fresh()->permissions->pluck('name')->all());
        $this->assertEqualsCanonicalizing(
            ['portal_content.view', 'portal_content.edit', 'portal_content.create', 'portal_content.publish', 'portal_content.delete'],
            $editor->fresh()->permissions->pluck('name')->all(),
        );
        $this->assertEqualsCanonicalizing(['settings.edit', 'portal_content.manage'], $settings->fresh()->permissions->pluck('name')->all());

        $direct = $direct->fresh();
        $this->assertTrue($direct->hasDirectPermission('portal_content.publish'));
        $this->assertFalse($direct->hasDirectPermission('portal_content.manage'));
    }
}
