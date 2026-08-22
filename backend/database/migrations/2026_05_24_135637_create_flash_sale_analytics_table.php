<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /**
         * Table: flash_sale_analytics Daily performance aggregates per flash sale submission. Computed by nightly job from flash_sale_orders. ColumnTypeNullDefaultDescriptionidUUID PKNOgen_random_uuid()flash_sale_idUUID FK→flash_salesNOflash_sale_submission_idUUID FK→flash_sale_submissionsNOvendor_idUUID FK→vendor_profilesNODenormalizeddateDATENOunits_soldINTNO0gross_revenue_centsBIGINTNO0At flash pricerevenue_at_normal_price_centsBIGINTNO0What it would have been without discountdiscount_given_centsBIGINTNO0Customer savingsplatform_commission_centsBIGINTNO0vendor_payout_centsBIGINTNO0viewsINTNO0Product page views during saleadd_to_cart_countINTNO0conversion_rateDECIMAL(6,4)NO0orders / viewscreated_atTIMESTAMPTZNOnow()
         */
        Schema::create('flash_sale_analytics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('flash_sale_id');
            $table->uuid('flash_sale_submission_id');
            $table->uuid('vendor_id');
            $table->date('date');
            $table->integer('units_sold');
            $table->char('currency', 3);
            $table->bigInteger('gross_revenue');
            $table->bigInteger('revenue_at_normal_price');
            $table->bigInteger('discount_given');
            $table->bigInteger('platform_commission');
            $table->bigInteger('vendor_payout');
            $table->integer('views');
            $table->integer('add_to_cart_count');
            $table->decimal('conversion_rate', 6, 4);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flash_sale_analytics');
    }
};
