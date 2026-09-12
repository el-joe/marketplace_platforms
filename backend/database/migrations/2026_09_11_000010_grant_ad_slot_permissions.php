<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const GUARD = 'admin';

    private const NEW_PERMISSIONS = [
        'ad_slots.view',
        'ad_slots.create',
        'ad_slots.edit',
        'ad_slots.delete',
        'ad_bookings.view',
        'ad_bookings.review',
        'ad_bookings.manage',
        'ad_bookings.finance',
    ];

    public function up(): void
    {
        foreach (self::NEW_PERMISSIONS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => self::GUARD]);
        }

        $legacyPermission = Permission::where('name', 'ad_campaigns.edit')
            ->where('guard_name', self::GUARD)
            ->first();

        if ($legacyPermission) {
            $roleIds = DB::table('role_has_permissions')
                ->where('permission_id', $legacyPermission->id)
                ->pluck('role_id');

            $roles = Role::where('guard_name', self::GUARD)->whereIn('id', $roleIds)->get();

            foreach ($roles as $role) {
                $role->givePermissionTo(self::NEW_PERMISSIONS);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('guard_name', self::GUARD)
            ->whereIn('name', self::NEW_PERMISSIONS)
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
