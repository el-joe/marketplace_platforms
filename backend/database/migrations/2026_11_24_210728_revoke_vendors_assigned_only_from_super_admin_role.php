<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Client test report, point 17: `vendors.assigned_only` is a restriction granted per
 * admin, never a system-role grant (see RolesAndPermissionsSeeder). It had ended up on
 * the super_admin role, so every super admin saw vendors as "restricted profiles" and
 * the acquisition-agent card / assign button were hidden.
 */
return new class extends Migration
{
    private const GUARD = 'admin';

    private const PERMISSION = 'vendors.assigned_only';

    public function up(): void
    {
        if (! Permission::where('name', self::PERMISSION)->where('guard_name', self::GUARD)->exists()) {
            return;
        }

        Role::where('guard_name', self::GUARD)
            ->where('name', 'super_admin')
            ->get()
            ->each(function (Role $role): void {
                if ($role->hasPermissionTo(self::PERMISSION, self::GUARD)) {
                    $role->revokePermissionTo(self::PERMISSION);
                }
            });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Intentionally irreversible: re-granting a restriction to super_admin is never desired.
    }
};
