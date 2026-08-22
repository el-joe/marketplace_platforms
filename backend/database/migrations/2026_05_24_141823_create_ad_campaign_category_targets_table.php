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
         * Table: ad_campaign_category_targets Categories this campaign targets for placement (category page sponsored slots). ColumnTypeNullDefaultDescriptionidUUID PKNOgen_random_uuid()ad_campaign_idUUID FK→ad_campaignsNOcategory_idUUID FK→categoriesNObid_override_centsBIGINTYESNULLPer-category bidis_activeBOOLEANNOtruecreated_atTIMESTAMPTZNOnow()
         */
        Schema::create('ad_campaign_category_targets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('ad_campaign_id')->index();
            $table->uuid('category_id')->index();
            $table->bigInteger('bid_override')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ad_campaign_category_targets');
    }
};
