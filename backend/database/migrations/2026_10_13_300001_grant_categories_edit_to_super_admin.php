<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const GUARD = 'admin';

    /**
     * The categories page (fixed-commission modal, category edit, commissions) is gated by
     * `categories.edit`. Make sure the permission exists and the super_admin role holds it
     * explicitly, so it does not depend only on the Gate::before super-admin bypass.
     */
    public function up(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'categories.edit', 'guard_name' => self::GUARD]);

        Role::where('guard_name', self::GUARD)->where('name', 'super_admin')
            ->each(fn (Role $role) => $role->givePermissionTo($permission));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Intentionally empty: the permission is part of the base permission set.
    }
};
