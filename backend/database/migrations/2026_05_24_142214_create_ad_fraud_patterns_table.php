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
         * Table: ad_fraud_patterns Tracks suspicious click patterns per IP and session. ColumnTypeNullDefaultDescriptionidUUID PKNOgen_random_uuid()ip_addressVARCHAR(45)NOad_campaign_idUUID FK→ad_campaignsNOclicks_last_hourINTNO0clicks_last_24hINTNO0is_blockedBOOLEANNOfalseThis IP blocked from charging this campaignblocked_atTIMESTAMPTZYESNULLblock_reasonVARCHAR(100)YESNULLrapid_repeat_clicks, bot_user_agent, datacenter_ipcreated_atTIMESTAMPTZNOnow()updated_atTIMESTAMPTZNOnow()
         */
        Schema::create('ad_fraud_patterns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ip_address', 45);
            $table->uuid('ad_campaign_id');
            $table->integer('clicks_last_hour')->default(0);
            $table->integer('clicks_last_24h')->default(0);
            $table->boolean('is_blocked')->default(false);
            $table->timestampTz('blocked_at')->nullable();
            $table->string('block_reason', 100)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ad_fraud_patterns');
    }
};
