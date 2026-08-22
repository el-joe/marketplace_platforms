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
         * Table: flash_sale_price_history Tracks the original price over time to detect fake discounts. A vendor cannot inflate the price to 500 EGP the week before, then "discount" to 400 EGP and claim 20% off. ColumnTypeNullDefaultDescriptionidUUID PKNOgen_random_uuid()seller_listing_idUUID FK→seller_listingsNOprice_centsBIGINTNOPrice at this point in timecurrencyCHAR(3)NOrecorded_atTIMESTAMPTZNOnow()recorded_byVARCHAR(20)NOsystemsystem for automatic snapshots, admin for manual
         */
        Schema::create('flash_sale_price_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('vendor_listing_id')->index();
            $table->bigInteger('price');
            $table->char('currency', 3);
            $table->timestampTz('recorded_at');
            $table->enum('recorded_by', ['system', 'admin']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flash_sale_price_histories');
    }
};
