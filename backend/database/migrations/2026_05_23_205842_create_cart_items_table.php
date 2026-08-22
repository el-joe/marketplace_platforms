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
         * cart_items ColumnTypeNullDescriptionidUUID PKNOcart_idUUID FK→cartsNOvendor_listing_idUUID FK→vendor_listingsNOSingle FK — carries variant + seller + countryquantityINTNOunit_price_centsBIGINTNOSnapshot at add-to-cart timeadded_atTIMESTAMPTZNO
         */
        Schema::create('cart_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('cart_id')->index();
            $table->uuid('vendor_listing_id')->index()->nullable();
            $table->integer('quantity');
            $table->bigInteger('unit_price');
            $table->timestampTz('added_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
