<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Vendors report exceptional carrier fees per a set of destination cities
     * (which may span multiple shipping zones, or include unzoned cities),
     * not a single pre-assigned zone. Replace destination_zone_id with
     * city_ids so the admin review screen can detect zones per city.
     */
    public function up(): void
    {
        Schema::table('vendor_exceptional_zone_alerts', function (Blueprint $table) {
            // warehouse_id's FK relies on vendor_zone_alerts_warehouse_zone_idx as its
            // supporting index (no other index covers warehouse_id alone) — add a
            // replacement before dropping that composite index.
            $table->index('warehouse_id');
        });

        Schema::table('vendor_exceptional_zone_alerts', function (Blueprint $table) {
            $table->dropForeign(['destination_zone_id']);
            $table->dropForeign(['created_subsidy_id']);
            $table->dropIndex('vendor_zone_alerts_warehouse_zone_idx');
            $table->dropColumn(['destination_zone_id', 'created_subsidy_id']);

            $table->json('city_ids')->after('warehouse_id');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_exceptional_zone_alerts', function (Blueprint $table) {
            $table->dropColumn('city_ids');

            $table->foreignUuid('destination_zone_id')->after('warehouse_id')
                ->constrained('shipping_zones')->cascadeOnDelete();

            $table->foreignUuid('created_subsidy_id')->nullable()
                ->constrained('platform_shipping_subsidies')->nullOnDelete();

            $table->index(['warehouse_id', 'destination_zone_id'], 'vendor_zone_alerts_warehouse_zone_idx');
        });

        Schema::table('vendor_exceptional_zone_alerts', function (Blueprint $table) {
            $table->dropIndex(['warehouse_id']);
        });
    }
};
