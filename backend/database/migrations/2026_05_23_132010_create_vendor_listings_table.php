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
         * seller_listings The bridge between sellers and catalog variants, now country-scoped. ColumnTypeNullDescriptionidUUID PKNOseller_idUUID FK→seller_profilesNOproduct_variant_idUUID FK→product_variantsNOcountry_idUUID FK→countriesNOWhich country site this listing appears onprice_centsBIGINTNOIn country's base currencycompare_at_price_centsBIGINTYESStrikethrough "was" pricecost_price_centsBIGINTYESSeller's cost — private, never exposedcurrencyCHAR(3)NOMust match countries.base_currencyconditionVARCHAR(20)NOnew, like_new, good, acceptable, refurbishedcondition_notesTEXTYES"Minor scratch on back panel"fulfillment_modelVARCHAR(10)NOfbm, fbn, cross_dockseller_skuVARCHAR(100)YESSeller's own internal SKU referenceseller_notesTEXTYESInternal only, never shown to customerstatusVARCHAR(20)NOdraft, pending_review, active, paused, rejected, out_of_stock, archivedrejection_reasonTEXTYESWhy admin rejectedmax_order_quantityINTYESMax units per customer per orderlow_stock_thresholdINTNODefault 5. Alert fires below thisbuy_box_eligibleBOOLEANNOSeller can opt out of buy box competitionbuy_box_won_atTIMESTAMPTZYESLast time this listing won the buy boxtotal_soldINTNOUnits sold through this listingrating_avgDECIMAL(3,2)YESAvg rating when this seller fulfilledapproved_by_admin_idUUID FK→usersYESapproved_atTIMESTAMPTZYEScreated_atTIMESTAMPTZNOupdated_atTIMESTAMPTZNOdeleted_atTIMESTAMPTZYES
         */
        Schema::create('vendor_listings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('vendor_id')->index();
            $table->uuid('product_variant_id')->index();
            $table->uuid('country_id')->index();
            $table->bigInteger('price');
            // $table->bigInteger('compare_at_price')->nullable();
            $table->bigInteger('cost_price')->nullable();
            $table->char('currency', 3);
            $table->enum('condition', ['new', 'like_new', 'good', 'acceptable', 'refurbished']);
            $table->text('condition_notes')->nullable();
            $table->enum('fulfillment_model', ['fbm', 'fbn', 'cross_dock']);
            $table->string('vendor_sku', 100)->nullable();
            $table->text('vendor_notes')->nullable();
            $table->enum('status', ['draft', 'pending_review', 'active', 'paused', 'rejected', 'out_of_stock', 'archived']);
            $table->text('rejection_reason')->nullable();
            $table->integer('max_order_quantity')->nullable();
            $table->integer('low_stock_threshold')->default(5);
            // $table->boolean('buy_box_eligible')->default(true);
            // $table->timestampTz('buy_box_won_at')->nullable();
            $table->integer('total_sold')->default(0);
            $table->decimal('rating_avg', 3, 2)->nullable();
            $table->uuid('approved_by_admin_id')->nullable()->index();
            $table->timestampTz('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });


        /**
         * buy_box_winners Tracks which listing holds the buy box per variant per country. ColumnTypeNullDescriptionidUUID PKNOproduct_variant_idUUID FK→product_variantsNOcountry_idUUID FK→countriesNOBuy box is per-countryseller_listing_idUUID FK→seller_listingsNOThe winning listingscoreDECIMAL(8,4)NOCalculated score at win timeprice_scoreDECIMAL(5,4)YESPrice component of scorefulfillment_scoreDECIMAL(5,4)YESFBN/FBM componentrating_scoreDECIMAL(5,4)YESSeller rating componentavailability_scoreDECIMAL(5,4)YESStock/delivery speed componentcalculated_atTIMESTAMPTZNOLast calculation timenext_recalculate_atTIMESTAMPTZNOTrigger recalc when price/stock changes
         */

        // Schema::create('buy_box_winners', function (Blueprint $table) {
        //     $table->uuid('id')->primary();
        //     $table->uuid('product_variant_id')->index();
        //     $table->uuid('country_id')->index();
        //     $table->uuid('vendor_listing_id')->index();
        //     $table->decimal('score', 8, 4);
        //     $table->decimal('price_score', 5, 4)->nullable();
        //     $table->decimal('fulfillment_score', 5, 4)->nullable();
        //     $table->decimal('rating_score', 5, 4)->nullable();
        //     $table->decimal('availability_score', 5, 4)->nullable();
        //     $table->timestampTz('calculated_at');
        //     $table->timestampTz('next_recalculate_at')->nullable();
        //     $table->timestamps();
        // });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_listings');
        // Schema::dropIfExists('buy_box_winners');
    }
};
