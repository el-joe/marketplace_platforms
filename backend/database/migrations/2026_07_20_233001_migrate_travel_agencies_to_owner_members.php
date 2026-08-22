<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The travel_agency session guard now authenticates against
 * travel_agency_members instead of travel_agencies directly. This backfills
 * one owner member per existing travel_agencies row, reusing the same
 * email/password so existing agencies keep their current credentials.
 */
return new class extends Migration {
    public function up(): void
    {
        $agencies = DB::table('travel_agencies')->select('id', 'name', 'email', 'password', 'created_at', 'updated_at')->get();

        foreach ($agencies as $agency) {
            $exists = DB::table('travel_agency_members')
                ->where('travel_agency_id', $agency->id)
                ->where('is_owner', true)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('travel_agency_members')->insert([
                'id' => (string) Str::uuid(),
                'travel_agency_id' => $agency->id,
                'name' => $agency->name,
                'email' => $agency->email,
                'password' => $agency->password,
                'role' => 'travel_agency_owner',
                'is_owner' => true,
                'is_active' => true,
                'created_at' => $agency->created_at,
                'updated_at' => $agency->updated_at,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('travel_agency_members')->where('is_owner', true)->delete();
    }
};
