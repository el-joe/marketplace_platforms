<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * subsidy_cap remains the ceiling on admin exposure regardless of split_type.
     * warehouse_id scopes the rule to a specific warehouse; NULL = applies to all
     * warehouses (used for platform FBN warehouses without a dedicated rule).
     */
    public function up(): void
    {
        // Guarded with hasColumn checks: a prior run of this migration on this
        // environment partially committed (the column-add block succeeded, the
        // index-swap block failed on a MySQL FK/index dependency), leaving the
        // migration marked "Pending" while the columns already exist.
        if (!Schema::hasColumn('platform_shipping_subsidies', 'warehouse_id')) {
            Schema::table('platform_shipping_subsidies', function (Blueprint $table) {
                $table->foreignUuid('warehouse_id')->nullable()->after('id')
                    ->constrained('warehouses')->nullOnDelete()
                    ->comment('Which warehouse this gap rule applies to. NULL = applies to all warehouses (for platform FBN).');
            });
        }

        if (!Schema::hasColumn('platform_shipping_subsidies', 'split_type')) {
            Schema::table('platform_shipping_subsidies', function (Blueprint $table) {
                $table->enum('split_type', ['percentage', 'fixed'])->default('percentage')->after('subsidy_cap')
                    ->comment('percentage = vendor_share_pct + admin covers rest; fixed = fixed amounts');

                $table->unsignedSmallInteger('vendor_share_pct')->default(50)->after('split_type')
                    ->comment('Percentage of gap vendor absorbs (0-100). Only used when split_type=percentage');

                $table->bigInteger('vendor_fixed_amount')->default(0)->after('vendor_share_pct')
                    ->comment('Fixed amount vendor pays per delivery. Only used when split_type=fixed. BIGINT base currency.');

                $table->bigInteger('admin_fixed_amount')->default(0)->after('vendor_fixed_amount')
                    ->comment('Fixed amount admin absorbs per delivery. Only used when split_type=fixed. BIGINT base currency.');
            });
        }

        $indexes = collect(Schema::getIndexes('platform_shipping_subsidies'))->pluck('name');

        if ($indexes->contains('unique_zone_method_subsidy')) {
            Schema::table('platform_shipping_subsidies', function (Blueprint $table) {
                // MySQL ties the shipping_zone_id FK to this composite unique index (leftmost
                // column match), so the FK must be dropped before the index can be dropped.
                $table->dropForeign(['shipping_zone_id']);
                $table->dropUnique('unique_zone_method_subsidy');
                $table->unique(['warehouse_id', 'shipping_zone_id', 'shipping_method_id'], 'unique_warehouse_zone_method_subsidy');
                $table->foreign('shipping_zone_id')->references('id')->on('shipping_zones');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('platform_shipping_subsidies', function (Blueprint $table) {
            $table->dropForeign(['shipping_zone_id']);
            $table->dropUnique('unique_warehouse_zone_method_subsidy');
            $table->unique(['shipping_zone_id', 'shipping_method_id'], 'unique_zone_method_subsidy');
            $table->foreign('shipping_zone_id')->references('id')->on('shipping_zones');
        });

        Schema::table('platform_shipping_subsidies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_id');
            $table->dropColumn(['split_type', 'vendor_share_pct', 'vendor_fixed_amount', 'admin_fixed_amount']);
        });
    }
};
