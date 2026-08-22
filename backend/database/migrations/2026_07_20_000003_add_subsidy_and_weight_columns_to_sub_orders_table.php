<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * order_items has no shipping_fee column and no per-item shipping breakdown —
     * shipping is tracked once per sub_order (sub_orders.shipping). The prior
     * admin_subsidy/vendor_deduction/delivery_subsidy_ledgered columns were removed by
     * 2026_07_17_193631_drop_vendor_subsidy_feature.php along with the rest of the old
     * vendor-subsidy feature, so this reintroduces equivalent columns for the new
     * platform_shipping_subsidies-driven flow, plus the weight snapshot needed to apply
     * platform_shipping_subsidies.max_subsidy_weight_grams.
     */
    public function up(): void
    {
        Schema::table('sub_orders', function (Blueprint $table) {
            $table->bigInteger('admin_subsidy_amount')->default(0)->after('shipping')
                ->comment('Platform-covered share of this sub-order delivery fee, base currency units');
            $table->bigInteger('vendor_contribution_amount')->default(0)->after('admin_subsidy_amount')
                ->comment('Vendor-covered share of this sub-order delivery fee (vendor_covers_delivery gap), base currency units');
            $table->unsignedInteger('billable_weight_grams')->nullable()->after('vendor_contribution_amount')
                ->comment('Snapshot of MAX(actual, volumetric) weight used at order time for shipping subsidy calculation');
            $table->boolean('subsidy_ledgered')->default(false)->after('billable_weight_grams')
                ->comment('Set true once admin_subsidy_amount/vendor_contribution_amount have been posted to ledger_entries, to prevent double-posting');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sub_orders', function (Blueprint $table) {
            $table->dropColumn([
                'admin_subsidy_amount',
                'vendor_contribution_amount',
                'billable_weight_grams',
                'subsidy_ledgered',
            ]);
        });
    }
};
