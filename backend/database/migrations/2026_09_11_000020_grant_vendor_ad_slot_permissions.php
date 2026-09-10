<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const GUARD = 'vendor';

    private const NEW_PERMISSIONS = [
        'ad_slots.view',
        'ad_slots.book',
        'ad_slots.pay',
    ];

    public function up(): void
    {
        foreach (self::NEW_PERMISSIONS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => self::GUARD]);
        }

        // VERIFY: vendor owner role is named "vendor_owner" (guard vendor) per
        // database/seeders/VendorPermissionSeeder.php.
        $vendorOwner = Role::where('name', 'vendor_owner')->where('guard_name', self::GUARD)->first();

        if ($vendorOwner) {
            $vendorOwner->givePermissionTo(self::NEW_PERMISSIONS);
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
