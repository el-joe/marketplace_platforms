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
         * Table: paid_ad_slots Defines the available inventory of purchasable placements. ColumnTypeNullDefaultDescriptionidUUID PKNOgen_random_uuid()placement_definition_idUUID FK→banner_placement_definitionsNOMust have supports_vendor_ads = truecountry_idUUID FK→countriesYESNULLNULL = available in all countriesnameVARCHAR(150)NO"Homepage Hero Slot A — Egypt"slot_codeVARCHAR(50) UNIQUENOhp_hero_egy_apricing_modelVARCHAR(10)NOfixed_weekly, fixed_monthly, cpm, cpcbase_rate_centsBIGINTNOStarting pricecurrencyCHAR(3)NOmin_booking_daysINTNO7Minimum booking durationmax_booking_daysINTNO90Maximumis_availableBOOLEANNOtrueAdmin can pause slot availabilityrequires_approvalBOOLEANNOtrueCreative must be reviewed before going livemin_seller_tierVARCHAR(20)YESNULLNULL = all. gold = Gold+ onlynotes_for_vendorsTEXTYESNULLSpecs, guidelinescreated_by_admin_idUUID FK→usersNOcreated_atTIMESTAMPTZNOnow()updated_atTIMESTAMPTZNOnow()
         */
        Schema::create('paid_ad_slots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('placement_definition_id')->index();
            $table->uuid('country_id')->index()->nullable();
            $table->string('name', 150);
            $table->string('slot_code', 50)->unique();
            $table->enum('pricing_model', ['fixed_weekly', 'fixed_monthly', 'cpm', 'cpc']);
            $table->bigInteger('base_rate_cents');
            $table->char('currency', 3);
            $table->integer('min_booking_days');
            $table->integer('max_booking_days');
            $table->boolean('is_available');
            $table->boolean('requires_approval');
            $table->string('min_seller_tier', 20)->nullable();
            $table->text('notes_for_vendors')->nullable();
            $table->uuid('created_by_admin_id')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paid_ad_slots');
    }
};
