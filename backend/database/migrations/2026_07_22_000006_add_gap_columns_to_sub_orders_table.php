<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * admin_subsidy_amount and vendor_contribution_amount already exist on sub_orders
     * (added by 2026_07_20_000003_add_subsidy_and_weight_columns_to_sub_orders_table.php)
     * and represent the admin/vendor share of the gap computed here.
     */
    public function up(): void
    {
        Schema::table('sub_orders', function (Blueprint $table) {
            $table->bigInteger('carrier_shipping_cost')->default(0)->after('shipping')
                ->comment('Actual carrier cost for this delivery. = customer shipping in normal zones; > customer shipping in exceptional zones. BIGINT base currency.');

            $table->bigInteger('shipping_gap')->default(0)->after('carrier_shipping_cost')
                ->comment('carrier_shipping_cost - shipping (customer paid). Gap to be split. 0 in normal zones. BIGINT base currency.');

            $table->foreignUuid('exceptional_zone_subsidy_id')->nullable()->after('shipping_gap')
                ->constrained('platform_shipping_subsidies')->nullOnDelete()
                ->comment('Which subsidy rule was applied to compute the gap split');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sub_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('exceptional_zone_subsidy_id');
            $table->dropColumn(['carrier_shipping_cost', 'shipping_gap']);
        });
    }
};
