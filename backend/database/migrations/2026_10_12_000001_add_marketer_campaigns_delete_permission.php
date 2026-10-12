<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const GUARD = 'vendor';

    private const PERMISSION = 'marketer_campaigns.delete';

    public function up(): void
    {
        $permission = Permission::firstOrCreate(['name' => self::PERMISSION, 'guard_name' => self::GUARD]);

        // Grant to every vendor role that can already cancel campaigns.
        Role::where('guard_name', self::GUARD)
            ->whereHas('permissions', fn ($q) => $q->where('name', 'marketer_campaigns.cancel')->where('guard_name', self::GUARD))
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
