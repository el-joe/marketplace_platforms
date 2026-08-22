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
         * Table: ad_impressions Every time a sponsored product is shown. High-volume — partition by date. ColumnTypeNullDefaultDescriptionidUUID PKNOgen_random_uuid()ad_campaign_idUUID FK→ad_campaignsNOseller_listing_idUUID FK→seller_listingsNOWhich listing was shownuser_idUUID FK→usersYESNULLNULL for guestssession_idVARCHAR(100)NOplacement_codeVARCHAR(50)NOsearch_results, category_top, homepage_sponsoredsearch_queryVARCHAR(255)YESNULLWhat the customer searchedposition_shownINTNORank in results (1 = top)bid_at_impression_centsBIGINTNOCPC bid that won this impressionquality_score_at_impressionDECIMAL(3,2)NOScore at time of showingwas_clickedBOOLEANNOfalsewas_convertedBOOLEANNOfalseLed to a purchasecost_charged_centsBIGINTNO00 until clicked (CPC) or set on impression (CPM)country_idUUID FK→countriesNOdevice_typeVARCHAR(10)NOdesktop, mobile, appshown_atTIMESTAMPTZNOnow()clicked_atTIMESTAMPTZYESNULLconverted_atTIMESTAMPTZYESNULL
         */
        Schema::create('ad_impressions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('ad_campaign_id');
            $table->uuid('vendor_listing_id');
            $table->uuid('customer_id')->nullable();
            $table->string('session_id', 100);
            $table->string('placement_code', 50);
            $table->string('search_query', 255)->nullable();
            $table->integer('position_shown');
            $table->bigInteger('bid_at_impression_cents');
            $table->decimal('quality_score_at_impression', 3, 2);
            $table->boolean('was_clicked')->default(false);
            $table->boolean('was_converted')->default(false);
            $table->bigInteger('cost_charged_cents');
            $table->uuid('country_id');
            $table->string('device_type', 10);
            $table->timestampTz('shown_at')->useCurrent();
            $table->timestampTz('clicked_at')->nullable();
            $table->timestampTz('converted_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ad_impressions');
    }
};
