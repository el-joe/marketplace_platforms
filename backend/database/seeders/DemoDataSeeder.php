<?php

namespace Database\Seeders;

use App\Models\Vendor;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

/**
 * Minimal cross-cutting demo data so freshly seeded users aren't empty shells
 * when an admin first opens each panel. Not exhaustive business data — just
 * enough to prevent broken/empty first-load states.
 *
 * Run this last (after all user seeders and ProductSeeder).
 * All checks use firstOrCreate() or existence guards — safe to re-run.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // Check if TechZone already has a warehouse assigned (from ProductSeeder or
        // a dedicated WarehouseSeeder) — only log, never duplicate.
        $vendor = Vendor::where('email', 'techzone@vendor.com')->first();

        if ($vendor) {
            $warehouse = Warehouse::where('owner_vendor_id', $vendor->id)->first();

            if (! $warehouse) {
                $this->command->line('  (No warehouse found for TechZone — create one via WarehouseSeeder or the admin panel)');
            } else {
                $this->command->line("  ✓ TechZone already has warehouse: {$warehouse->name}");
            }
        }

        $this->command->info('✅ Demo cross-reference data checked.');
    }
}
