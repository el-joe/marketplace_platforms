<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * PM decision (WS4b, 2026-11-23): the admin permission sweep gated vendor
 * actions that `vendor_relations_admin` could previously perform without a
 * dedicated permission. Restore those abilities explicitly. Idempotent and a
 * no-op when the role does not exist on this environment.
 */
return new class extends Migration
{
    private const GUARD = 'admin';

    private const ROLE = 'vendor_relations_admin';

    private const PERMISSIONS = [
        'vendors.documents.verify',
        'vendors.blacklist',
        'vendors.strikes.issue',
        'vendors.payouts.hold',
    ];

    public function up(): void
    {
        $role = Role::where('name', self::ROLE)->where('guard_name', self::GUARD)->first();

        if ($role === null) {
            return;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => self::GUARD]);

            if (! $role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Intentionally left as a no-op: the role may have held these permissions
        // before this migration, so revoking them could remove legitimate access.
    }
};
