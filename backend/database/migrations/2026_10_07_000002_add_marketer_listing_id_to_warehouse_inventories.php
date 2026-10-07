<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('warehouse_inventories', 'marketer_listing_id')) {
            Schema::table('warehouse_inventories', function (Blueprint $table) {
                $table->foreignUuid('marketer_listing_id')->nullable()->after('admin_listing_id')->constrained('marketer_listings')->restrictOnDelete();
            });
        } else {
            // Column exists from a previous partial run — ensure the FK uses RESTRICT (not SET NULL),
            // which is required for columns referenced in CHECK constraints (MySQL error 3823).
            DB::statement('ALTER TABLE warehouse_inventories DROP FOREIGN KEY warehouse_inventories_marketer_listing_id_foreign');
            DB::statement('ALTER TABLE warehouse_inventories ADD CONSTRAINT warehouse_inventories_marketer_listing_id_foreign FOREIGN KEY (marketer_listing_id) REFERENCES marketer_listings (id) ON DELETE RESTRICT');
        }

        // Drop the old 2-way XOR constraint (may already be dropped on a retry).
        try {
            DB::statement('ALTER TABLE warehouse_inventories DROP CONSTRAINT chk_wi_listing_xor');
        } catch (\Exception) {
            // Constraint already dropped on a previous partial run.
        }

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
