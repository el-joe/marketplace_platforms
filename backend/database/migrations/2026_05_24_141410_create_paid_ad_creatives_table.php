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
         * Table: paid_ad_creatives The actual banner images submitted by vendors for a booking. Separate from the booking because a vendor may submit multiple versions and admin picks one. ColumnTypeNullDefaultDescriptionidUUID PKNOgen_random_uuid()paid_ad_booking_idUUID FK→paid_ad_bookingsNOseller_idUUID FK→seller_profilesNODenormalizedmedia_idUUID FK→mediaNODesktop creativemobile_media_idUUID FK→mediaYESNULLMobile varianttitle_enVARCHAR(255)YESNULLOverlay or alt texttitle_arVARCHAR(255)YESNULLcta_label_enVARCHAR(100)YESNULLcta_label_arVARCHAR(100)YESNULLdestination_urlVARCHAR(500)NOWhere the click goesdestination_typeVARCHAR(20)NOproduct, category, store, urldestination_reference_idUUIDYESNULLFK to product/category if applicablestatusVARCHAR(20)NOpendingpending, approved, rejected, active, replacedrejection_reasonTEXTYESNULLrejection_codeVARCHAR(50)YESNULLwrong_dimensions, policy_violation, low_quality, misleading_claimis_currentBOOLEANNOfalseWhich creative is currently runningreviewed_by_admin_idUUID FK→usersYESNULLreviewed_atTIMESTAMPTZYESNULLapproved_atTIMESTAMPTZYESNULLcreated_atTIMESTAMPTZNOnow()updated_atTIMESTAMPTZNOnow()
         */
        Schema::create('paid_ad_creatives', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('paid_ad_booking_id')->index();
            $table->uuid('vendor_id')->index();
            // $table->uuid('media_id')->index();
            // $table->uuid('mobile_media_id')->index()->nullable();
            $table->string('title_en', 255)->nullable();
            $table->string('title_ar', 255)->nullable();
            $table->string('cta_label_en', 100)->nullable();
            $table->string('cta_label_ar', 100)->nullable();
            $table->string('destination_url', 500);
            $table->string('destination_type', 20);
            $table->uuid('destination_reference_id')->nullable();
            $table->string('status', 20);
            $table->text('rejection_reason')->nullable();
            $table->string('rejection_code', 50)->nullable();
            $table->boolean('is_current')->default(false);
            $table->uuid('reviewed_by_admin_id')->index()->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paid_ad_creatives');
    }
};
