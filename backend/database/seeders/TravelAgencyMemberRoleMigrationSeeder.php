<?php

namespace Database\Seeders;

use App\Models\TravelAgencyMember;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Assigns the travel_agency_owner Spatie role to owner rows backfilled from
 * travel_agencies by the migrate_travel_agencies_to_owner_members migration.
 * Idempotent — skips members that already have a travel_agency-guard role.
 */
class TravelAgencyMemberRoleMigrationSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        DB::transaction(function () {
            TravelAgencyMember::query()->chunkById(100, function ($members) {
                foreach ($members as $member) {
                    if ($member->roles()->where('guard_name', 'travel_agency')->exists()) {
                        continue;
                    }

                    $roleName = $member->is_owner ? 'travel_agency_owner' : $member->role;

                    if (!$roleName) {
                        continue;
                    }

                    $member->assignRole($roleName);
                    $member->update(['role' => $roleName]);
                }
            });
        });

        $this->command->info('Travel agency member role → Spatie role migration complete.');
    }
}
