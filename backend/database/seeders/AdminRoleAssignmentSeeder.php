<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Assigns Spatie roles to the existing admin accounts created by AdminSeeder.
 * Does NOT create new Admin rows — only applies syncRoles() to existing ones.
 * syncRoles() is idempotent; re-running this seeder is always safe.
 */
class AdminRoleAssignmentSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $assignments = [
            'admin@admin.com'   => 'super_admin',
            'mohamed@admin.com' => 'operations_admin',
            'layla@admin.com'   => 'marketing_admin',
            'sara@admin.com'    => 'finance_admin',
        ];

        foreach ($assignments as $email => $roleName) {
            $admin = Admin::where('email', $email)->first();

            if (! $admin) {
                $this->command->warn("⚠  Admin {$email} not found — skipping. Run AdminSeeder first.");
                continue;
            }

            $admin->syncRoles([$roleName]);
            $this->command->line("  ✓ {$email} → {$roleName}");
        }

        $this->command->info('✅ Roles assigned to existing admin accounts.');
    }
}
