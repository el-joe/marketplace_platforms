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
         * Table: flash_sale_submissions Each vendor's product submission for a flash sale event. One row per seller_listing per flash sale. ColumnTypeNullDefaultDescriptionidUUID PKNOgen_random_uuid()flash_sale_idUUID FK→flash_salesNOseller_idUUID FK→seller_profilesNOseller_listing_idUUID FK→seller_listingsNOWhich product variant and which sellerstatusVARCHAR(30)NOdraftFull list belowflash_price_centsBIGINTNOThe discounted price vendor wants to offeroriginal_price_centsBIGINTNOCurrent price (snapshot at submission time)calculated_discount_pctDECIMAL(5,2)NO(original - flash) / original × 100 — computed on savereference_price_30d_centsBIGINTYESNULL30-day average price — used to verify real discountmax_quantity_totalINTNOTotal units available for the flash salemax_quantity_per_customerINTNO1Per-person limitquantity_soldINTNO0Running counter during the salequantity_remainingINT GENERATEDNOmax_quantity_total - quantity_sold. Computedflash_price_currencyCHAR(3)NOMust match country currencyrejection_reasonVARCHAR(500)YESNULLWhy admin rejectedrejection_codeVARCHAR(50)YESNULLdiscount_too_low, fake_discount, not_eligible, slot_full, policy_violationsubmitted_atTIMESTAMPTZYESNULLWhen vendor pressed "Submit"reviewed_atTIMESTAMPTZYESNULLWhen admin approved or rejectedreviewed_by_admin_idUUID FK→usersYESNULLapproved_atTIMESTAMPTZYESNULLsold_out_atTIMESTAMPTZYESNULLWhen quantity_sold reached maxadmin_notesTEXTYESNULLInternal notes (not shown to vendor)vendor_notesTEXTYESNULLVendor's notes on submissioncreated_atTIMESTAMPTZNOnow()updated_atTIMESTAMPTZNOnow()
         */
        Schema::create('flash_sale_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('flash_sale_id')->index();
            $table->uuid('vendor_id')->index();
            $table->uuid('vendor_listing_id')->index();
            $table->string('status', 30);
            $table->bigInteger('flash_price');
            $table->bigInteger('original_price');
            $table->decimal('calculated_discount_pct', 5, 2);
            $table->bigInteger('reference_price_30d')->nullable();
            $table->integer('max_quantity_total');
            $table->integer('max_quantity_per_customer')->default(1);
            $table->integer('quantity_sold')->default(0);
            $table->integer('quantity_remaining')->virtualAs('max_quantity_total - quantity_sold');
            $table->char('flash_price_currency', 3);
            $table->string('rejection_reason', 500)->nullable();
            $table->string('rejection_code', 50)->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->uuid('reviewed_by_admin_id')->index()->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('sold_out_at')->nullable();
            $table->text('admin_notes')->nullable();
            $table->text('vendor_notes')->nullable();
            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flash_sale_submissions');
    }
};
