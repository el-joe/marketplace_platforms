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
         * Table: flash_sales The event itself — created and owned by the platform admin. ColumnTypeNullDefaultDescriptionidUUID PKNOgen_random_uuid()country_idUUID FK→countriesYESNULLNULL = all countries. Set = country-specificname_enVARCHAR(200)NO"Ramadan Flash Sale"name_arVARCHAR(200)NO"فلاش سيل رمضان"description_enTEXTYESNULLShown to vendors explaining the eventdescription_arTEXTYESNULLbanner_media_idUUID FK→mediaYESNULLBanner shown on the flash sale blockmobile_banner_media_idUUID FK→mediaYESNULLstatusVARCHAR(30)NOdraftFull list belowsubmission_opens_atTIMESTAMPTZNOWhen vendors can start submitting productssubmission_closes_atTIMESTAMPTZNODeadline for vendor submissionsreview_deadline_atTIMESTAMPTZNOAdmin must approve/reject all by this timesale_starts_atTIMESTAMPTZNOWhen the sale goes live to customerssale_ends_atTIMESTAMPTZNOWhen the sale closesmin_discount_pctDECIMAL(5,2)NO10.00Vendors must offer at least this % off. Enforced on submissionmax_products_per_sellerINTYESNULLNULL = unlimitedeligible_categoriesJSONBYESNULLNULL = all. Array of category_ids if restrictedeligible_seller_tiersJSONBYESNULLNULL = all. e.g. ["gold","platinum"]min_seller_ratingDECIMAL(3,2)YESNULLNULL = no minimumcommission_override_pctDECIMAL(5,2)YESNULLPlatform can charge different commission during flash salesis_featuredBOOLEANNOfalseHomepage hero treatmentis_exclusiveBOOLEANNOfalseProducts must not be on sale elsewhere during the eventprice_drop_requiredBOOLEANNOtrueDiscount must be below the product's 30-day average pricemax_total_slotsINTYESNULLCap on total approved products across all vendorsapproved_slots_countINTNO0Denormalized countercreated_by_admin_idUUID FK→usersNOupdated_by_admin_idUUID FK→usersYESNULLcancelled_atTIMESTAMPTZYESNULLcancellation_reasonTEXTYESNULLcreated_atTIMESTAMPTZNOnow()updated_atTIMESTAMPTZNOnow()
         */
        Schema::create('flash_sales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('country_id')->index()->nullable();
            $table->string('name_en', 200);
            $table->string('name_ar', 200);
            $table->text('description_en')->nullable();
            $table->text('description_ar')->nullable();
            // $table->uuid('banner_media_id')->nullable()->index();
            // $table->uuid('mobile_banner_media_id')->nullable()->index();
            $table->string('status', 30);
            $table->timestampTz('submission_opens_at');
            $table->timestampTz('submission_closes_at');
            $table->timestampTz('review_deadline_at');
            $table->timestampTz('sale_starts_at');
            $table->timestampTz('sale_ends_at');
            $table->decimal('min_discount_pct', 5, 2);
            $table->integer('max_products_per_seller')->nullable();
            $table->json('eligible_categories')->nullable();
            $table->json('eligible_seller_tiers')->nullable();
            $table->decimal('min_seller_rating', 3, 2)->nullable();
            $table->decimal('commission_override_pct', 5, 2)->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_exclusive')->default(false);
            $table->boolean('price_drop_required')->default(true);
            $table->integer('max_total_slots')->nullable();
            $table->integer('approved_slots_count')->default(0);
            $table->uuid('created_by_admin_id')->index();
            $table->uuid('updated_by_admin_id')->index()->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flash_sales');
    }
};
