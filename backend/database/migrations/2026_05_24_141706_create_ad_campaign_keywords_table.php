<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /**
         * Table: ad_campaign_keywords Keywords this campaign bids on. Used for search placement. ColumnTypeNullDefaultDescriptionidUUID PKNOgen_random_uuid()ad_campaign_idUUID FK→ad_campaignsNOkeywordVARCHAR(150)NO"iphone 15", "smartphone"keyword_normalizedVARCHAR(150)NOLowercase, no diacritics — for matchingmatch_typeVARCHAR(10)NObroadexact, phrase, broadbid_override_centsBIGINTYESNULLNULL = use campaign bidis_negativeBOOLEANNOfalseNegative keyword = exclude from this searchis_activeBOOLEANNOtruecreated_atTIMESTAMPTZNOnow()
         */
        Schema::create('ad_campaign_keywords', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('ad_campaign_id')->index();
            $table->string('keyword', 150);
            $table->string('keyword_normalized', 150)->index();
            $table->enum('match_type', ['broad', 'phrase', 'exact']);
            $table->bigInteger('bid_override')->nullable();
            $table->boolean('is_negative')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ad_campaign_keywords');
    }
};
