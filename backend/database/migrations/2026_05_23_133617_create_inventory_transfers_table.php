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
         * inventory_transfers Moving stock between warehouses. ColumnTypeNullDescriptionidUUID PKNOtransfer_numberVARCHAR(30) UNIQUENOTRF-2026-0042source_warehouse_idUUID FK→warehousesNOdestination_warehouse_idUUID FK→warehousesNOseller_idUUID FK→seller_profilesNOStock ownerstatusVARCHAR(20)NOdraft, in_transit, received, cancelledinitiated_by_user_idUUID FK→usersNOcarrierVARCHAR(100)YEStracking_numberVARCHAR(100)YESexpected_arrival_dateDATEYESshipped_atTIMESTAMPTZYESreceived_atTIMESTAMPTZYESnotesTEXTYEScreated_atTIMESTAMPTZNOupdated_atTIMESTAMPTZNO
         * inventory_transfer_items ColumnTypeNullDescriptionidUUID PKNOinventory_transfer_idUUID FK→inventory_transfersNOseller_listing_idUUID FK→seller_listingsNOquantity_requestedINTNOquantity_receivedINTNODefault 0 until arrivaldamaged_quantityINTNODefault 0condition_notesTEXTYES
         */
        Schema::create('inventory_transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('transfer_number', 30)->unique();
            $table->uuid('source_warehouse_id');
            $table->uuid('destination_warehouse_id');
            $table->uuid('vendor_id');
            $table->enum('status', ['draft', 'in_transit', 'received', 'cancelled']);
            $table->uuid('initiated_by_user_id')->index();
            $table->string('carrier', 100)->nullable();
            $table->string('tracking_number', 100)->nullable();
            $table->date('expected_arrival_date')->nullable();
            $table->timestampTz('shipped_at')->nullable();
            $table->timestampTz('received_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_transfer_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('inventory_transfer_id')->index();
            $table->uuid('vendor_listing_id')->index();
            $table->integer('quantity_requested');
            $table->integer('quantity_received')->default(0);
            $table->integer('damaged_quantity')->default(0);
            $table->text('condition_notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_transfers');
    }
};
