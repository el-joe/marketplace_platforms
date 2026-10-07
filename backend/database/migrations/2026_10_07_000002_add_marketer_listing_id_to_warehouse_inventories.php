<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_inventories', function (Blueprint $table) {
            $table->foreignUuid('marketer_listing_id')->nullable()->after('admin_listing_id')->constrained('marketer_listings')->nullOnDelete();
        });

        // Drop the old 2-way XOR constraint.
        DB::statement('ALTER TABLE warehouse_inventories DROP CONSTRAINT chk_wi_listing_xor');

        // Add new 3-way XOR constraint: exactly one of the three FK columns must be set.
        DB::statement('
            ALTER TABLE warehouse_inventories
            ADD CONSTRAINT chk_wi_listing_xor CHECK (
                (vendor_listing_id IS NOT NULL AND admin_listing_id IS NULL    AND marketer_listing_id IS NULL) OR
                (vendor_listing_id IS NULL    AND admin_listing_id IS NOT NULL AND marketer_listing_id IS NULL) OR
                (vendor_listing_id IS NULL    AND admin_listing_id IS NULL    AND marketer_listing_id IS NOT NULL)
            )
        ');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE warehouse_inventories DROP CONSTRAINT chk_wi_listing_xor');

        DB::statement('
            ALTER TABLE warehouse_inventories
            ADD CONSTRAINT chk_wi_listing_xor CHECK (
                (vendor_listing_id IS NOT NULL AND admin_listing_id IS NULL) OR
                (vendor_listing_id IS NULL     AND admin_listing_id IS NOT NULL)
            )
        ');

        Schema::table('warehouse_inventories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('marketer_listing_id');
        });
    }
};
