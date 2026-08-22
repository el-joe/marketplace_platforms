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
         * Table: flash_sale_orders When an order includes a flash sale item, this junction table links the order item to the flash sale submission. Needed for accurate performance reporting per flash sale event. ColumnTypeNullDefaultDescriptionidUUID PKNOgen_random_uuid()flash_sale_submission_idUUID FK→flash_sale_submissionsNOflash_sale_idUUID FK→flash_salesNODenormalizedorder_item_idUUID FK→order_itemsNOquantityINTNOUnits of this item bought in this orderflash_price_centsBIGINTNOSnapshot of flash price at purchase timeoriginal_price_centsBIGINTNOSnapshot of regular pricediscount_amount_centsBIGINTNOSavings per unitcreated_atTIMESTAMPTZNOnow()
         */
        Schema::create('flash_sale_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('flash_sale_submission_id');
            $table->uuid('flash_sale_id');
            $table->uuid('order_item_id');
            $table->integer('quantity');
            $table->char('currency', 3);
            $table->bigInteger('flash_price');
            $table->bigInteger('original_price');
            $table->bigInteger('discount_amount');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flash_sale_orders');
    }
};
