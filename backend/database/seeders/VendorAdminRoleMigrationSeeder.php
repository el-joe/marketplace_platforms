<?php

namespace Database\Seeders;

use App\Models\VendorAdmin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Migrates the legacy vendor_admins.role enum into the Spatie model_has_roles
 * pivot. Idempotent — skips vendor admins that already have a vendor-guard role.
 */
class VendorAdminRoleMigrationSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $roleMap = [
            'owner' => 'vendor_owner',
            'manager' => 'vendor_manager',
            'staff' => 'vendor_staff',
        ];

        DB::transaction(function () use ($roleMap) {
            VendorAdmin::query()->chunkById(100, function ($vendorAdmins) use ($roleMap) {
                foreach ($vendorAdmins as $vendorAdmin) {
                    if ($vendorAdmin->roles()->where('guard_name', 'vendor')->exists()) {
                        continue;
                    }

                    $roleValue = $vendorAdmin->getRawOriginal('role');
                    $roleName = $roleMap[$roleValue] ?? null;

                    if (! $roleName) {
                        continue;
                    }

                    $vendorAdmin->assignRole($roleName);
                    $vendorAdmin->update([
                        'role' => $roleName,
                        'is_owner' => $roleValue === 'owner',
                    ]);
                }
            });
        });

        $this->command->info('Vendor admin role → Spatie role migration complete.');
    }
}
