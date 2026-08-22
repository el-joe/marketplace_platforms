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
         * Table: ad_quality_scores Recalculated weekly per campaign. ColumnTypeNullDefaultDescriptionidUUID PKNOgen_random_uuid()ad_campaign_idUUID FK→ad_campaignsNOseller_listing_idUUID FK→seller_listingsNOscoreDECIMAL(3,2)NO1.00–10.00ctr_scoreDECIMAL(3,2)NOHistorical click-through rate componentrelevance_scoreDECIMAL(3,2)NOKeyword match qualitylanding_scoreDECIMAL(3,2)NOProduct page completeness (images, description, reviews)seller_scoreDECIMAL(3,2)NOSeller rating and SLA compliancecalculated_atTIMESTAMPTZNOnow()
         */
        Schema::create('ad_quality_scores', function (Blueprint $table) {
            $table->id();
            $table->uuid('ad_campaign_id');
            $table->uuid('vendor_listing_id');
            $table->decimal('score', 3, 2);
            $table->decimal('ctr_score', 3, 2);
            $table->decimal('relevance_score', 3, 2);
            $table->decimal('landing_score', 3, 2);
            $table->decimal('vendor_score', 3, 2);
            $table->timestampTz('calculated_at')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ad_quality_scores');
    }
};
