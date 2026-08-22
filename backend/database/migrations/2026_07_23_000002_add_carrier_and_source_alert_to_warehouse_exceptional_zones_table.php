<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('warehouse_exceptional_zones', 'carrier_id')) {
            Schema::table('warehouse_exceptional_zones', function (Blueprint $table) {
                $table->foreignUuid('carrier_id')->nullable()->after('destination_zone_id')
                    ->constrained('shipping_carriers')->nullOnDelete()
                    ->comment('Specific carrier this exceptional zone applies to, NULL = all carriers');

                $table->foreignUuid('source_alert_id')->nullable()->after('is_active')
                    ->constrained('vendor_exceptional_zone_alerts')->nullOnDelete()
                    ->comment('The vendor alert that led to this record, NULL if admin created it directly');
            });
        }

        $indexes = collect(Schema::getIndexes('warehouse_exceptional_zones'))->pluck('name');

        if ($indexes->contains('unique_warehouse_destination_zone')) {
            Schema::table('warehouse_exceptional_zones', function (Blueprint $table) {
                // Same MySQL FK/leftmost-index quirk as the subsidies migration:
                // warehouse_id's FK is tied to this composite unique index (leftmost column).
                $table->dropForeign(['warehouse_id']);
                $table->dropUnique('unique_warehouse_destination_zone');
                $table->unique(['warehouse_id', 'destination_zone_id', 'carrier_id'], 'unique_warehouse_zone_carrier');
                $table->foreign('warehouse_id')->references('id')->on('warehouses')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        $indexes = collect(Schema::getIndexes('warehouse_exceptional_zones'))->pluck('name');

        if ($indexes->contains('unique_warehouse_zone_carrier')) {
            Schema::table('warehouse_exceptional_zones', function (Blueprint $table) {
                $table->dropForeign(['warehouse_id']);
                $table->dropUnique('unique_warehouse_zone_carrier');
                $table->unique(['warehouse_id', 'destination_zone_id'], 'unique_warehouse_destination_zone');
                $table->foreign('warehouse_id')->references('id')->on('warehouses')->cascadeOnDelete();
            });
        }

        Schema::table('warehouse_exceptional_zones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_alert_id');
            $table->dropConstrainedForeignId('carrier_id');
        });
    }
};
