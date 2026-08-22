<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('vendor_city_shipping_surcharges', 'warehouse_id')) {
            Schema::table('vendor_city_shipping_surcharges', function (Blueprint $table) {
                $table->foreignUuid('warehouse_id')->nullable()->after('vendor_id')->constrained()->cascadeOnDelete();
                $table->unique(['vendor_id', 'warehouse_id']);
            });
        }

        if (Schema::hasColumn('vendor_city_shipping_surcharges', 'city_id')) {
            $foreignKeys = collect(DB::select(
                "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'vendor_city_shipping_surcharges' AND TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'city_id' AND REFERENCED_TABLE_NAME IS NOT NULL"
            ))->pluck('CONSTRAINT_NAME');

            Schema::table('vendor_city_shipping_surcharges', function (Blueprint $table) use ($foreignKeys) {
                if ($foreignKeys->isNotEmpty()) {
                    $table->dropForeign(['city_id']);
                }
                $table->dropUnique(['vendor_id', 'city_id']);
                $table->dropColumn('city_id');
            });
        }

        // Drop the one pre-existing dev row that has no warehouse_id (this feature
        // launched same-day and has no production data to preserve).
        DB::table('vendor_city_shipping_surcharges')->whereNull('warehouse_id')->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendor_city_shipping_surcharges', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropUnique(['vendor_id', 'warehouse_id']);
            $table->dropColumn('warehouse_id');
            $table->foreignUuid('city_id')->after('vendor_id')->constrained()->cascadeOnDelete();
            $table->unique(['vendor_id', 'city_id']);
        });
    }
};
