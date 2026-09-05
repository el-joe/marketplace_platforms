<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates all Spatie permissions and roles for the 'admin' and 'vendor' guards.
 * Uses firstOrCreate() throughout — fully idempotent and safe to re-run.
 *
 * Guard scope:
 *   admin  → Admin model   (admins table)
 *   vendor → VendorAdmin model (vendor_admins table)
 *
 * NOTE: VendorAdmin must use Spatie\Permission\Traits\HasRoles (already added).
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $adminPermissions = Permission::where('guard_name', 'admin')->pluck('name')->toArray();

        $vendorPermissions = [
            'listings.manage',
            'orders.manage',
            'payouts.view',
            'team.manage',
            'documents.manage',
        ];

        $rolesMap = [
            // ── admin guard ───────────────────────────────────────────────
            [
                'name'        => 'super_admin',
                'guard'       => 'admin',
                // vendors.assigned_only is a manually-granted restriction, never a
                // system-role grant — excluded even from the otherwise-full super_admin set.
                'permissions' => array_values(array_diff($adminPermissions, ['vendors.assigned_only'])),
            ],
            [
                'name'  => 'operations_admin',
                'guard' => 'admin',
                'permissions' => [
                    'dashboard.view', 'orders.view', 'orders.edit', 'orders.cancel',
                    'vendors.view', 'delivery.view', 'delivery.edit',
                    'warehouses.view', 'warehouses.edit', 'packaging.manage',
                    'returns.view', 'returns.manage',
                    'admin_listings.view', 'admin_listings.edit',
                ],
            ],
            [
                'name'  => 'finance_admin',
                'guard' => 'admin',
                'permissions' => [
                    'dashboard.view', 'orders.view', 'orders.refund',
                    'settings.payment_gateways',
                ],
            ],
            [
                'name'  => 'marketing_admin',
                'guard' => 'admin',
                'permissions' => [
                    'dashboard.view',
                    'flash_sales.view', 'flash_sales.create', 'flash_sales.edit',
                    'flash_sales.review_submissions',
                    'vouchers.view', 'vouchers.create', 'vouchers.edit', 'vouchers.delete',
                    'marketer_campaigns.view', 'marketer_campaigns.approve', 'marketer_campaigns.reject',
                    'marketers.view', 'marketers.manage',
                ],
            ],
            [
                'name'  => 'vendor_relations_admin',
                'guard' => 'admin',
                'permissions' => [
                    'dashboard.view', 'vendors.view', 'vendors.approve',
                    'vendors.suspend', 'vendors.edit', 'products.view', 'products.edit',
                ],
            ],

            // ── vendor guard ──────────────────────────────────────────────
            [
                'name'        => 'vendor_owner',
                'guard'       => 'vendor',
                'permissions' => $vendorPermissions,
            ],
            [
                'name'  => 'vendor_manager',
                'guard' => 'vendor',
                'permissions' => ['listings.manage', 'orders.manage', 'payouts.view'],
            ],
            [
                'name'  => 'vendor_staff',
                'guard' => 'vendor',
                'permissions' => ['orders.manage'],
            ],
        ];

        DB::transaction(function () use ($rolesMap, $adminPermissions, $vendorPermissions): void {
            foreach (['admin' => $adminPermissions, 'vendor' => $vendorPermissions] as $guard => $perms) {
                foreach ($perms as $perm) {
                    Permission::firstOrCreate(['name' => $perm, 'guard_name' => $guard]);
                }
            }

            foreach ($rolesMap as $def) {
                $role = Role::firstOrCreate(['name' => $def['name'], 'guard_name' => $def['guard']]);
                // syncPermissions scoped to the correct guard automatically
                $role->syncPermissions(
                    collect($def['permissions'])->map(
                        fn ($p) => Permission::where('name', $p)->where('guard_name', $def['guard'])->first()
                    )->filter()->values()->all()
                );
            }
        });

        $this->command->info('✅ Roles & permissions seeded for admin and vendor guards.');
    }
}
