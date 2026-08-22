<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guard = 'admin';
        $allPerms = Permission::where('guard_name', $guard)->pluck('name')->toArray();

        // ── super_admin: all permissions ──────────────────────────────────────
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => $guard]);
        $superAdmin->syncPermissions($allPerms);

        // ── admin: all except a few sensitive ones ─────────────────────────────
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => $guard]);
        $adminRole->syncPermissions(array_diff($allPerms, [
            'admins.delete',
            'roles.delete',
            'countries.launch',
            'admins.impersonate',
        ]));

        // ── operations: vendors + orders + tickets + disputes ─────────────────
        $ops = Role::firstOrCreate(['name' => 'operations', 'guard_name' => $guard]);
        $ops->syncPermissions(array_values(array_filter(
            $allPerms,
            fn($p) =>
            str_starts_with($p, 'vendors.') ||
            str_starts_with($p, 'orders.') ||
            str_starts_with($p, 'tickets.') ||
            str_starts_with($p, 'disputes.')
        )));

        // ── finance ───────────────────────────────────────────────────────────
        $finance = Role::firstOrCreate(['name' => 'finance', 'guard_name' => $guard]);
        $finance->syncPermissions(array_values(array_filter(
            $allPerms,
            fn($p) =>
            str_starts_with($p, 'payouts.') ||
            str_starts_with($p, 'commissions.') ||
            str_starts_with($p, 'ledger.')
        )));

        // ── marketing ─────────────────────────────────────────────────────────
        $marketing = Role::firstOrCreate(['name' => 'marketing', 'guard_name' => $guard]);
        $marketing->syncPermissions(array_values(array_filter(
            $allPerms,
            fn($p) =>
            str_starts_with($p, 'banners.') ||
            str_starts_with($p, 'flash_sales.') ||
            str_starts_with($p, 'coupons.') ||
            str_starts_with($p, 'ad_campaigns.') ||
            str_starts_with($p, 'page_builder.')
        )));

        // ── support ───────────────────────────────────────────────────────────
        $support = Role::firstOrCreate(['name' => 'support', 'guard_name' => $guard]);
        $support->syncPermissions(array_values(array_filter(
            $allPerms,
            fn($p) =>
            str_starts_with($p, 'tickets.') ||
            str_starts_with($p, 'reviews.') ||
            str_starts_with($p, 'disputes.')
        )));

        // ── catalog ───────────────────────────────────────────────────────────
        $catalog = Role::firstOrCreate(['name' => 'catalog', 'guard_name' => $guard]);
        $catalog->syncPermissions(array_values(array_filter(
            $allPerms,
            fn($p) =>
            str_starts_with($p, 'products.') ||
            str_starts_with($p, 'categories.') ||
            str_starts_with($p, 'brands.') ||
            str_starts_with($p, 'attributes.')
        )));

        // ── readonly: all *.view permissions ──────────────────────────────────
        $readonly = Role::firstOrCreate(['name' => 'readonly', 'guard_name' => $guard]);
        $readonly->syncPermissions(array_values(array_filter(
            $allPerms,
            fn($p) =>
            str_ends_with($p, '.view')
        )));

        // ── Assign super_admin role to admin@admin.com ────────────────────────
        $superAdminUser = Admin::where('email', 'admin@admin.com')->first();
        if ($superAdminUser) {
            $superAdminUser->syncRoles(['super_admin']);
        }

        $this->command->info('Roles seeded: super_admin, admin, operations, finance, marketing, support, catalog, readonly.');
    }
}
