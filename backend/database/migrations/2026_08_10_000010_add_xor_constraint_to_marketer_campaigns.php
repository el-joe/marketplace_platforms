<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $exists = DB::selectOne(
            "SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'marketer_campaigns'
             AND CONSTRAINT_NAME = 'chk_campaign_listing_xor'"
        );

        if (! $exists) {
            DB::statement("ALTER TABLE marketer_campaigns ADD CONSTRAINT chk_campaign_listing_xor
                CHECK (
                    (vendor_listing_id IS NOT NULL AND admin_listing_id IS NULL) OR
                    (vendor_listing_id IS NULL AND admin_listing_id IS NOT NULL)
                )");
        }
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE marketer_campaigns DROP CHECK chk_campaign_listing_xor");
    }
};
