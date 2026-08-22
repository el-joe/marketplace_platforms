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
         * Table: shipment_tracking_events ColumnTypeDescriptionidUUID PKshipment_idUUID FK → shipments.idstatusVARCHAR(100)Carrier status stringdescriptionTEXT"Arrived at sorting facility"locationVARCHAR(255) NULLoccurred_atTIMESTAMPFrom carrierraw_payloadJSONB NULLFull webhook for debuggingcreated_atTIMESTAMP
         */
        Schema::create('shipment_tracking_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('shipment_id')->index();
            $table->string('status', 100);
            $table->text('description');
            $table->string('location', 255)->nullable();
            $table->timestamp('occurred_at');
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipment_tracking_events');
    }
};
