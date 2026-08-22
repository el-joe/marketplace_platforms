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
         * Table: ad_campaign_products Which seller listings this campaign promotes. ColumnTypeNullDefaultDescriptionidUUID PKNOgen_random_uuid()ad_campaign_idUUID FK→ad_campaignsNOseller_listing_idUUID FK→seller_listingsNOThe listing being promotedseller_idUUID FK→seller_profilesNODenormalizedproduct_variant_idUUID FK→product_variantsNODenormalizedis_activeBOOLEANNOtruecreated_atTIMESTAMPTZNOnow()
         */
        Schema::create('ad_campaign_products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('ad_campaign_id')->index();
            $table->uuid('product_variant_id')->index();
            $table->uuid('vendor_id')->index();
            $table->uuid('vendor_listing_id')->index();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ad_campaign_products');
    }
};
