<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * WS12 #24: permissions for the "product link / QR commission discount" rules page. Granted to super_admin and to
 * the roles that already edit platform commission (categories.edit is the screen where commission is set).
 */
return new class extends Migration
{
    private const GUARD = 'admin';

    /** new permission => existing permissions whose holders receive it */
    private const PERMISSIONS = [
        'product_link_commission.view' => ['categories.view', 'categories.edit'],
        'product_link_commission.manage' => ['categories.edit'],
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name => $grantedAlongside) {
            $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => self::GUARD]);

            Role::where('guard_name', self::GUARD)
                ->where(fn ($query) => $query
                    ->where('name', 'super_admin')
                    ->orWhereHas('permissions', fn ($q) => $q->whereIn('name', $grantedAlongside)->where('guard_name', self::GUARD)))
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
