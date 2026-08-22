<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('platform_shipping_subsidies', 'carrier_id')) {
            Schema::table('platform_shipping_subsidies', function (Blueprint $table) {
                $table->foreignUuid('carrier_id')->nullable()->after('warehouse_id')
                    ->constrained('shipping_carriers')->nullOnDelete()
                    ->comment('Which carrier this subsidy applies to. NULL = all carriers.');
            });
        }

        $indexes = collect(Schema::getIndexes('platform_shipping_subsidies'))->pluck('name');

        if ($indexes->contains('unique_warehouse_zone_method_subsidy')) {
            Schema::table('platform_shipping_subsidies', function (Blueprint $table) {
                // Same MySQL FK/leftmost-index quirk: warehouse_id's FK is tied to this
                // composite unique index (leftmost column), so it must be dropped first.
                $table->dropForeign(['warehouse_id']);
                $table->dropUnique('unique_warehouse_zone_method_subsidy');
                $table->unique(
                    ['warehouse_id', 'shipping_zone_id', 'shipping_method_id', 'carrier_id'],
                    'unique_warehouse_zone_method_carrier_subsidy'
                );
                $table->foreign('warehouse_id')->references('id')->on('warehouses')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        $indexes = collect(Schema::getIndexes('platform_shipping_subsidies'))->pluck('name');

        if ($indexes->contains('unique_warehouse_zone_method_carrier_subsidy')) {
            Schema::table('platform_shipping_subsidies', function (Blueprint $table) {
                $table->dropForeign(['warehouse_id']);
                $table->dropUnique('unique_warehouse_zone_method_carrier_subsidy');
                $table->unique(['warehouse_id', 'shipping_zone_id', 'shipping_method_id'], 'unique_warehouse_zone_method_subsidy');
                $table->foreign('warehouse_id')->references('id')->on('warehouses')->nullOnDelete();
            });
        }

        Schema::table('platform_shipping_subsidies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('carrier_id');
        });
    }
};
