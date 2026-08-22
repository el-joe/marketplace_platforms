<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     * inbound_shipments
     * Vendor sending stock to FBN warehouse. ColumnTypeNullDescriptionidUUID PKNOshipment_codeVARCHAR(50) UNIQUENOIB-2026-00123seller_idUUID FK→seller_profilesNOwarehouse_idUUID FK→warehousesNODestinationcountry_idUUID FK→countriesNOWhich country's warehousestatusVARCHAR(20)NOdraft, submitted, in_transit, arrived, receiving, received, rejectedcarrierVARCHAR(100)YEStracking_numberVARCHAR(100)YESexpected_arrival_dateDATEYESarrived_atTIMESTAMPTZYESreceived_atTIMESTAMPTZYESreceived_by_admin_idUUID FK→usersYESnotesTEXTYEScreated_atTIMESTAMPTZNOupdated_atTIMESTAMPTZNO
     * inbound_shipment_items ColumnTypeNullDescriptionidUUID PKNOinbound_shipment_idUUID FK→inbound_shipmentsNOseller_listing_idUUID FK→seller_listingsNOWhich listing this stock is forexpected_quantityINTNOreceived_quantityINTNODefault 0damaged_quantityINTNODefault 0condition_notesTEXTYES
     */
    public function up(): void
    {
        Schema::create('inbound_shipments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('shipment_code', 50)->unique();
            $table->uuid('vendor_id');
            $table->uuid('warehouse_id');
            $table->uuid('country_id');
            $table->enum('status', ['draft', 'submitted', 'in_transit', 'arrived', 'receiving', 'received', 'rejected']);
            $table->string('carrier', 100)->nullable();
            $table->string('tracking_number', 100)->nullable();
            $table->date('expected_arrival_date')->nullable();
            $table->timestampTz('arrived_at')->nullable();
            $table->timestampTz('received_at')->nullable();
            $table->uuid('received_by_admin_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('inbound_shipment_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('inbound_shipment_id');
            $table->uuid('vendor_listing_id');
            $table->integer('expected_quantity');
            $table->integer('received_quantity')->default(0);
            $table->integer('damaged_quantity')->default(0);
            $table->text('condition_notes')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inbound_shipments');
    }
};
