<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Client test report, point 19: several sidebar sections had no permission of their own
 * (they rode on `pages.*`, `countries.*` or `settings.*`), so a role could not be given,
 * say, the blog without also getting the page builder. Each section now has its own
 * permissions. Every new permission goes to super_admin and to every role that already
 * holds the permission that previously opened the same screen, so nobody loses access.
 */
return new class extends Migration
{
    private const GUARD = 'admin';

    /** new permission => existing permissions whose holders receive it */
    private const PERMISSIONS = [
        'blog.view' => ['pages.view'],
        'blog.manage' => ['pages.manage'],
        'help_center.view' => ['pages.view'],
        'help_center.manage' => ['pages.manage'],
        'adsupport.view' => ['pages.view'],
        'adsupport.manage' => ['pages.manage'],
        'live_streams.view' => ['pages.view'],
        'live_streams.manage' => ['pages.manage'],
        'cities.view' => ['countries.view'],
        'cities.manage' => ['countries.edit'],
        'currencies.view' => ['countries.view'],
        'currencies.manage' => ['settings.edit'],
        'newsletter.view' => ['settings.view'],
        'newsletter.manage' => ['settings.edit'],
        'footer_settings.manage' => ['settings.content'],
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name => $grantedAlongside) {
            $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => self::GUARD]);

            Role::where('guard_name', self::GUARD)
                ->where(fn ($query) => $query
                    ->where('name', 'super_admin')
                    ->orWhereHas(
                        'permissions',
                        fn ($q) => $q->whereIn('name', $grantedAlongside)->where('guard_name', self::GUARD)
                    ))
                ->get()
                ->each(fn (Role $role) => $role->givePermissionTo($permission));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('guard_name', self::GUARD)->whereIn('name', array_keys(self::PERMISSIONS))->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
