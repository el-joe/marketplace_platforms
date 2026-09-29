<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    public function up(): void
    {
        // Fix listings misfiled as merchant_fbp (or any other value) despite being fulfilled via FBN.
        DB::statement("
            UPDATE vendor_listings
            SET global_system_type = 'express_fbn'
            WHERE fulfillment_model = 'fbn'
              AND global_system_type != 'express_fbn'
        ");

        // Backfill global_system_type for FBM listings, which previously had no matching case.
        DB::statement("
            UPDATE vendor_listings
            SET global_system_type = 'merchant_fbm'
            WHERE fulfillment_model = 'fbm'
        ");
    }

    public function down(): void
    {
        Log::warning('sync_global_system_type_on_vendor_listings migration is not reversible: it corrected data integrity issues on vendor_listings.global_system_type, not a schema change.');
    }
};
