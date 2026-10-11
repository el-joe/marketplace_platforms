<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const GUARD = 'admin';

    private const PERMISSION = 'commission_rules.manage';

    /**
     * Writer permission for vendor-specific rows in the `commissions` table. Those rows override
     * every other commission source at checkout, so they get their own permission instead of
     * piggy-backing on categories.edit. Existing holders of categories.edit keep parity.
     */
    public function up(): void
    {
        Permission::firstOrCreate(['name' => self::PERMISSION, 'guard_name' => self::GUARD]);

        $source = Permission::where('name', 'categories.edit')->where('guard_name', self::GUARD)->first();

        if ($source) {
            $roleIds = DB::table('role_has_permissions')->where('permission_id', $source->id)->pluck('role_id');

            Role::where('guard_name', self::GUARD)->whereIn('id', $roleIds)
                ->each(fn (Role $role) => $role->givePermissionTo(self::PERMISSION));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('guard_name', self::GUARD)->where('name', self::PERMISSION)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
