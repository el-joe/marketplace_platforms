<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permissions for the admin "Static Pages" module (Admin\StaticPageController).
 * Each one is granted to every admin role that already holds the matching FAQ
 * permission, since both modules manage public site content.
 */
return new class extends Migration
{
    private const GUARD = 'admin';

    /** new permission => existing permission whose holders receive it */
    private const PERMISSIONS = [
        'static_pages.view' => 'faqs.view',
        'static_pages.create' => 'faqs.create',
        'static_pages.edit' => 'faqs.edit',
        'static_pages.delete' => 'faqs.delete',
    ];

    public function up(): void
    {
        foreach (self::PERMISSIONS as $name => $grantedAlongside) {
            $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => self::GUARD]);

            Role::where('guard_name', self::GUARD)
                ->whereHas('permissions', fn ($q) => $q->where('name', $grantedAlongside)->where('guard_name', self::GUARD))
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
