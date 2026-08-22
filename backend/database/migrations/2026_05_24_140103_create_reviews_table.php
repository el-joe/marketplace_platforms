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
         * reviews ColumnTypeNullDescriptionidUUID PKNOproduct_idUUID FK→productsNOAlways on the product (global)seller_listing_idUUID FK→seller_listingsYESWhich seller fulfilled — carries countryuser_idUUID FK→usersNOorder_item_idUUID FK→order_itemsYESVerified purchase linkcountry_idUUID FK→countriesNOWhich country's customer left the reviewratingTINYINTNO1–5titleVARCHAR(255)YESbodyTEXTYESis_verified_purchaseBOOLEANNOstatusVARCHAR(20)NOpending, published, flagged, hidden, rejectedai_flag_reasonVARCHAR(255)YESmoderated_by_admin_idUUID FK→usersYEShelpful_countINTNOnot_helpful_countINTNOcreated_atTIMESTAMPTZNOupdated_atTIMESTAMPTZNOdeleted_atTIMESTAMPTZYES
         */
        Schema::create('reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id')->index();
            $table->uuid('vendor_listing_id')->index()->nullable();
            $table->uuid('customer_id')->index();
            $table->uuid('order_item_id')->index()->nullable();
            $table->uuid('country_id')->index();
            $table->tinyInteger('rating');
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->boolean('is_verified_purchase')->default(false);
            $table->string('status', 20);
            $table->string('ai_flag_reason')->nullable();
            $table->uuid('moderated_by_admin_id')->index()->nullable();
            $table->integer('helpful_count')->default(0);
            $table->integer('not_helpful_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
