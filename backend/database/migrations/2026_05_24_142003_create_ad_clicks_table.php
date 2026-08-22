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
         * Table: ad_clicks Separate from impressions for clarity and to prevent fraud analysis complications. ColumnTypeNullDefaultDescriptionidUUID PKNOgen_random_uuid()ad_impression_idUUID FK→ad_impressionsNOThe impression that was clickedad_campaign_idUUID FK→ad_campaignsNODenormalizedseller_listing_idUUID FK→seller_listingsNODenormalizeduser_idUUID FK→usersYESNULLsession_idVARCHAR(100)NOip_addressVARCHAR(45)NOFor click fraud detectionuser_agentTEXTYESNULLis_fraud_suspectBOOLEANNOfalseFlagged by fraud detectionfraud_reasonVARCHAR(100)YESNULLsame_ip_rapid_clicks, bot_patterncost_centsBIGINTNOWhat was charged to the vendorcountry_idUUID FK→countriesNOclicked_atTIMESTAMPTZNOnow()
         */
        Schema::create('ad_clicks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('ad_impression_id');
            $table->uuid('ad_campaign_id');
            $table->uuid('vendor_listing_id');
            $table->uuid('customer_id')->nullable();
            $table->string('session_id', 100);
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->boolean('is_fraud_suspect')->default(false);
            $table->string('fraud_reason', 100)->nullable();
            $table->bigInteger('cost_cents');
            $table->uuid('country_id');
            $table->timestampTz('clicked_at')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ad_clicks');
    }
};
