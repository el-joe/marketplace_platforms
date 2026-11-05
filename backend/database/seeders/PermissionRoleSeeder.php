<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionRoleSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles/permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guard = 'admin';

        // ── Create "Super Admin" role ─────────────────────────────────────────
        /** @var Role $superAdmin */
        $superAdmin = Role::firstOrCreate(
            ['name' => 'super_admin', 'guard_name' => $guard],
        );

        $permissions = Permission::where('guard_name', $guard)->pluck('id')->toArray();

        // Assign all permissions to Super Admin
        $superAdmin->permissions()->sync($permissions);

        // Only the bootstrap account is promoted. Promoting every admin on each deploy would silently
        // turn all restricted staff into super admins.
        Admin::where('email', 'admin@admin.com')->get()
            ->each(fn ($admin) => $admin->assignRole($superAdmin));

        $this->command->info('Permissions, roles, and Super Admin assignment seeded successfully.');
    }
}
