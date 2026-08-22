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
         * inventory_movements Append-only audit trail of every stock change. ColumnTypeNullDescriptionidUUID PKNOwarehouse_inventory_idUUID FK→warehouse_inventoryNOmovement_typeVARCHAR(20)NOinbound, outbound, reservation, release, adjustment, damage, return, transferquantity_deltaINTNOSigned: +10 received, -1 soldquantity_afterINTNOSnapshot of quantity_on_hand after this movementreference_typeVARCHAR(50)YESorder, inbound_shipment, transfer, adjustmentreference_idUUIDYESPolymorphic to the source recordreasonVARCHAR(255)YESFree text for manual adjustmentscreated_by_user_idUUID FK→usersNOcreated_atTIMESTAMPTZNOImmutable — no updated_at
         */
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('warehouse_inventory_id')->index();
            $table->enum('movement_type', ['inbound', 'outbound', 'reservation', 'release', 'adjustment', 'damage', 'return', 'transfer']);
            $table->integer('quantity_delta'); // Signed: +10 received, -1 sold
            $table->integer('quantity_after'); // Snapshot of quantity_on_hand after this movement
            $table->enum('reference_type', ['order', 'inbound_shipment', 'transfer', 'adjustment'])->nullable();
            $table->uuid('reference_id')->nullable();
            $table->string('reason', 255)->nullable();
            $table->uuid('created_by_user_id')->index();
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
