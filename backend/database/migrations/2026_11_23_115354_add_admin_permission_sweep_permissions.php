<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Admin permission sweep (client item #16b, 2026-11-23): sections that had no
 * permission gate (or only a `*.view` gate on mutating actions) get their own
 * permissions. Each new permission goes to super_admin and to every role that
 * already holds the permission that previously opened the same screen (the
 * sidebar permission), so nobody loses access they were meant to have.
 * Mutating permissions are only ever derived from an existing mutating permission.
 */
return new class extends Migration
{
    private const GUARD = 'admin';

    /** new permission => existing permissions whose holders receive it (empty = super_admin only) */
    private const PERMISSIONS = [
        'orders.edit' => ['orders.cancel'],
        'subscriptions.view' => ['transactions.view'],
        'subscriptions.manage' => ['transactions.edit'],
        'fbn.view' => ['warehouses.view'],
        'fbn.manage' => ['warehouses.manage'],
        'fbn_storage_fees.view' => ['warehouses.view'],
        'fbn_storage_fees.manage' => ['warehouses.manage'],
        'contracts.view' => ['classifieds.view'],
        'contracts.manage' => [],
        'classifieds.manage' => [],
        'acquisition_commissions.view' => ['vendors.view'],
        'acquisition_commissions.manage' => ['vendors.edit'],
        'delivery.view' => ['payouts.view'],
        'delivery.manage' => ['payouts.approve'],
        'wallets.view' => ['payouts.view'],
        'wallets.manage' => ['payouts.approve'],
        'shipping_companies.view' => ['settings.view'],
        'ai.view' => ['dashboard.view'],
        'ai.manage' => ['settings.edit'],
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name => $grantedAlongside) {
            $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => self::GUARD]);

            Role::where('guard_name', self::GUARD)
                ->where(fn ($query) => $query
                    ->where('name', 'super_admin')
                    ->when($grantedAlongside !== [], fn ($query) => $query->orWhereHas(
                        'permissions',
                        fn ($q) => $q->whereIn('name', $grantedAlongside)->where('guard_name', self::GUARD)
                    )))
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
