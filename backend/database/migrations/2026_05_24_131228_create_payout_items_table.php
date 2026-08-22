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
         * Table: payout_items Links a payout to the specific sub-orders being paid for. ColumnTypeDescriptionidUUID PKpayout_idUUID FK → payouts.idsub_order_idUUID FK → sub_orders.idgross_centsBIGINTcommission_centsBIGINTnet_centsBIGINT
         */
        Schema::create('payout_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('payout_id')->index();
            $table->uuid('sub_order_id')->index();
            $table->bigInteger('gross');
            $table->bigInteger('commission');
            $table->bigInteger('net');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payout_items');
    }
};
