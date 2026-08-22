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
         * warehouse_inventory Stock per seller listing per warehouse. Single FK to seller_listings which already carries country context. ColumnTypeNullDescriptionidUUID PKNOseller_listing_idUUID FK→seller_listingsNOCarries seller + variant + countrywarehouse_idUUID FK→warehousesNOPhysical locationquantity_on_handINTNOPhysical units present. CHECK >= 0quantity_reservedINTNOLocked for in-flight orders. CHECK >= 0quantity_availableINT GENERATEDNOon_hand - reserved. Computed columnquantity_inboundINTNOExpected from inbound shipmentsquantity_damagedINTNOQuarantined, unsellablebin_locationVARCHAR(50)YESA-12-3 aisle/shelf/binreorder_pointINTYESSuggest reorder when available drops belowlast_counted_atTIMESTAMPTZYESLast cycle countcreated_atTIMESTAMPTZNOupdated_atTIMESTAMPTZNO
         */
        Schema::create('warehouse_inventories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('vendor_listing_id')->index();
            $table->uuid('warehouse_id')->index();
            $table->integer('quantity_on_hand')->default(0);
            $table->integer('quantity_reserved')->default(0);
            $table->integer('quantity_available')->virtualAs('quantity_on_hand - quantity_reserved');
            $table->integer('quantity_inbound')->default(0);
            $table->integer('quantity_damaged')->default(0);
            $table->string('bin_location', 50)->nullable();
            $table->integer('reorder_point')->nullable();
            $table->timestampTz('last_counted_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_inventories');
    }
};
