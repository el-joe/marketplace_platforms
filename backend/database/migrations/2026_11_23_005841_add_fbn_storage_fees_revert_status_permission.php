<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Client item #9: reverting an FBN storage fee (Paid -> Invoiced,
 * Invoiced -> Pending) is a finance correction, so it gets its own
 * permission. Granted to super_admin only; other roles opt in via Roles.
 */
return new class extends Migration
{
    private const GUARD = 'admin';

    private const PERMISSION = 'fbn_storage_fees.revert_status';

    public function up(): void
    {
        $permission = Permission::firstOrCreate(['name' => self::PERMISSION, 'guard_name' => self::GUARD]);

        Role::where('guard_name', self::GUARD)
            ->where('name', 'super_admin')
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permission));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('guard_name', self::GUARD)->where('name', self::PERMISSION)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
