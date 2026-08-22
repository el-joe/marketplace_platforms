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
         * cart_inventory_locks 15-minute hold during checkout.ColumnTypeNullDescriptionidUUID PKNOcart_idUUID FK→cartsNOwarehouse_inventory_idUUID FK→warehouse_inventoryNOseller_listing_idUUID FK→seller_listingsNODenormalized for fast releasequantityINTNOexpires_atTIMESTAMPTZNOcreated_atTIMESTAMPTZNO
         */
        Schema::create('cart_inventory_locks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('cart_id')->index();
            $table->uuid('warehouse_inventory_id')->index();
            $table->uuid('vendor_listing_id')->index();
            $table->integer('quantity');
            $table->timestampTz('expires_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cart_inventory_locks');
    }
};
