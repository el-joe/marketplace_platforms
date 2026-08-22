<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class VendorPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guard = 'vendor';

        $permissions = [
            'listings.view',
            'listings.create',
            'listings.edit',
            'listings.delete',
            'listings.pricing.edit',
            'listings.stock.edit',
            'listings.publish',
            'orders.view',
            'orders.process',
            'orders.cancel',
            'orders.export',
            'returns.view',
            'returns.process',
            'disputes.view',
            'disputes.respond',
            'finance.view',
            'finance.payouts.view',
            'finance.invoices.view',
            'finance.invoices.export',
            'products.view',
            'products.create',
            'products.edit',
            'products.delete',
            'reviews.view',
            'reviews.respond',
            'customers.view',
            'team.view',
            'team.invite',
            'team.manage',
            'settings.view',
            'settings.edit',
            'documents.upload',
            'roles.view',
            'roles.create',
            'roles.edit',
            'roles.delete',
            'marketer_campaigns.view',
            'marketer_campaigns.create',
            'marketer_campaigns.edit',
            'marketer_campaigns.cancel',
            'marketer_profile.view',
            'marketer_profile.edit',
            'marketer_invitations.view',
            'marketer_invitations.respond',
            'marketer_reports.view',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => $guard]);
        }

        $managerPermissions = [
            'listings.view', 'listings.create', 'listings.edit', 'listings.stock.edit',
            'orders.view', 'orders.process', 'orders.export',
            'returns.view', 'returns.process', 'disputes.view', 'disputes.respond',
            'finance.view', 'finance.invoices.view',
            'products.view', 'products.create', 'products.edit',
            'reviews.view', 'reviews.respond',
            'customers.view', 'settings.view', 'documents.upload',
            'marketer_campaigns.view', 'marketer_campaigns.create', 'marketer_campaigns.edit', 'marketer_campaigns.cancel',
            'marketer_profile.view', 'marketer_profile.edit',
            'marketer_invitations.view', 'marketer_invitations.respond',
            'marketer_reports.view',
        ];

        $staffPermissions = [
            'listings.view', 'listings.create', 'listings.edit', 'listings.stock.edit',
            'orders.view', 'orders.process',
            'returns.view', 'disputes.view',
            'products.view', 'products.create', 'products.edit',
            'reviews.view', 'reviews.respond',
            'customers.view',
            'marketer_campaigns.view',
            'marketer_profile.view',
            'marketer_invitations.view', 'marketer_invitations.respond',
            'marketer_reports.view',
        ];

        $vendorOwner = Role::where('name', 'vendor_owner')->where('guard_name', $guard)->first();
        $vendorManager = Role::where('name', 'vendor_manager')->where('guard_name', $guard)->first();
        $vendorStaff = Role::where('name', 'vendor_staff')->where('guard_name', $guard)->first();

        if ($vendorOwner) {
            $vendorOwner->syncPermissions($permissions);
        }

        if ($vendorManager) {
            $vendorManager->syncPermissions($managerPermissions);
        }

        if ($vendorStaff) {
            $vendorStaff->syncPermissions($staffPermissions);
        }

        $countPermissions = Permission::where('guard_name', $guard)->count();
        $this->command->info('Vendor permissions seeded: ' . $countPermissions . ' permissions (guard: ' . $guard . ').');
    }
}
