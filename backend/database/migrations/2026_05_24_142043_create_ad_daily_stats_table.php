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
         * Table: ad_daily_stats Aggregated daily stats per campaign. Written by nightly job. Never query ad_impressions directly for dashboards. ColumnTypeNullDefaultDescriptionidUUID PKNOgen_random_uuid()ad_campaign_idUUID FK→ad_campaignsNOseller_listing_idUUID FK→seller_listingsYESNULLNULL = campaign total. Set = per-product breakdowncountry_idUUID FK→countriesNOdateDATENOimpressionsINTNO0clicksINTNO0valid_clicksINTNO0After fraud filterconversionsINTNO0Orders attributedspend_centsBIGINTNO0Total chargedrevenue_attributed_centsBIGINTNO0GMV from attributed ordersctrDECIMAL(6,4)NO0clicks / impressionscvrDECIMAL(6,4)NO0conversions / clicksacosDECIMAL(6,4)NO0Advertising cost of sale: spend / revenueaverage_positionDECIMAL(4,2)NO0Average rank shown atcreated_atTIMESTAMPTZNOnow()
         */
        Schema::create('ad_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->uuid('ad_campaign_id');
            $table->uuid('vendor_listing_id')->nullable();
            $table->uuid('country_id');
            $table->date('date');
            $table->integer('impressions')->default(0);
            $table->integer('clicks')->default(0);
            $table->integer('valid_clicks')->default(0);
            $table->integer('conversions')->default(0);
            $table->bigInteger('spend_cents')->default(0);
            $table->bigInteger('revenue_attributed_cents')->default(0);
            $table->decimal('ctr', 6, 4)->default(0);
            $table->decimal('cvr', 6, 4)->default(0);
            $table->decimal('acos', 6, 4)->default(0);
            $table->decimal('average_position', 4, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ad_daily_stats');
    }
};
