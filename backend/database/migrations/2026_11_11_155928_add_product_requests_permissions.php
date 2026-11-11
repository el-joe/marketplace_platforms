<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permissions for the admin "Product Requests" queue (Admin\ProductRequestController).
 * Viewing is granted to every role that can view products; reviewing (approve by
 * creating the product, or reject) to every role that can create products.
 */
return new class extends Migration
{
    private const GUARD = 'admin';

    /** new permission => existing permission whose holders receive it */
    private const PERMISSIONS = [
        'product_requests.view' => 'products.view',
        'product_requests.review' => 'products.create',
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
