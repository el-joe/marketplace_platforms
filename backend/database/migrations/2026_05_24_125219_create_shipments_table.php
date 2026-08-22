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
         * Table: shipments The actual physical shipment (one per sub-order, sometimes split). ColumnTypeDescriptionidUUID PKsub_order_idUUID FK → sub_orders.idcarrier_idUUID FK → shipping_carriers.idtracking_numberVARCHAR(100)awb_label_urlVARCHAR(500) NULLPrintable labelweight_gramsINTFinal weightdimensionsVARCHAR(50) NULL"30x20x15"shipping_cost_actualBIGINTWhat carrier charged usstatusENUM('label_created','picked_up','in_transit','out_for_delivery','delivered','failed','returned')picked_up_atTIMESTAMP NULLdelivered_atTIMESTAMP NULLdelivery_proof_media_idUUID FK → media.id NULLPhoto/signaturedelivery_otpVARCHAR(10) NULLOTP confirmationcreated_at, updated_atTIMESTAMPS
         */
        Schema::create('shipments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('sub_order_id')->index();
            $table->uuid('carrier_id')->index();
            $table->string('tracking_number', 100);
            $table->string('awb_label_url', 500)->nullable();
            $table->integer('weight_grams')->nullable();
            $table->string('dimensions', 50)->nullable();
            $table->bigInteger('shipping_cost_actual')->nullable();
            $table->enum('status', ['label_created', 'picked_up', 'in_transit', 'out_for_delivery', 'delivered', 'failed', 'returned']);
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('delivery_otp', 10)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
