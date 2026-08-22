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
         * Table: idempotency_keys Generic idempotency for any sensitive operation. ColumnTypeDescriptionidUUID PKkeyVARCHAR(255) UNIQUEClient or gateway providedrequest_hashCHAR(64)SHA-256 of request bodyoperation_typeVARCHAR(100)payment, refund, payoutreference_typeVARCHAR(50) NULLreference_idUUID NULLresponse_statusINT NULLHTTP status of original responseresponse_bodyJSONB NULLFor replayingexpires_atTIMESTAMPUsually 24 hourscreated_atTIMESTAMP
         */
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key', 255)->unique();
            $table->char('request_hash', 64);
            $table->string('operation_type', 100);
            $table->string('reference_type', 50)->nullable();
            $table->uuid('reference_id')->nullable();
            $table->integer('response_status')->nullable();
            $table->json('response_body')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
