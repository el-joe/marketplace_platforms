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
         * Table: paid_ad_bookings Each vendor's booking of a slot. ColumnTypeNullDefaultDescriptionidUUID PKNOgen_random_uuid()booking_referenceVARCHAR(50) UNIQUENOPAB-2026-0042paid_ad_slot_idUUID FK→paid_ad_slotsNOWhich slotseller_idUUID FK→seller_profilesNOWhich vendorcountry_idUUID FK→countriesNODenormalized from slotpricing_modelVARCHAR(10)NOSnapshot at booking timebooked_fromDATENOInclusive startbooked_untilDATENOInclusive endagreed_rate_centsBIGINTNONegotiated price (may differ from base)currencyCHAR(3)NOstatusVARCHAR(20)NOpendingpending, approved, active, paused, completed, cancelled, rejectedrejection_reasonTEXTYESNULLpayment_statusVARCHAR(20)NOunpaidunpaid, invoiced, paid, refundedpayment_transaction_idUUID FK→payment_transactionsYESNULLinvoiced_atTIMESTAMPTZYESNULLpaid_atTIMESTAMPTZYESNULLimpressions_deliveredINTNO0Running counterclicks_deliveredINTNO0cpm_impressions_billedINTNO0For CPM: impressions actually chargedtotal_charged_centsBIGINTNO0Final amount billed at endapproved_by_admin_idUUID FK→usersYESNULLapproved_atTIMESTAMPTZYESNULLnotesTEXTYESNULLInternal ops notescreated_atTIMESTAMPTZNOnow()updated_atTIMESTAMPTZNOnow()
         */
        Schema::create('paid_ad_bookings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('booking_reference', 50)->unique();
            $table->uuid('paid_ad_slot_id');
            $table->uuid('vendor_id');
            $table->uuid('country_id');
            $table->string('pricing_model', 10);
            $table->date('booked_from');
            $table->date('booked_until');
            $table->bigInteger('agreed_rate_cents');
            $table->char('currency', 3);
            $table->string('status', 20);
            $table->text('rejection_reason')->nullable();
            $table->string('payment_status', 20);
            $table->uuid('payment_transaction_id')->nullable();
            $table->timestampTz('invoiced_at')->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->integer('impressions_delivered')->default(0);
            $table->integer('clicks_delivered')->default(0);
            $table->integer('cpm_impressions_billed')->default(0);
            $table->bigInteger('total_charged')->default(0);
            $table->uuid('approved_by_admin_id')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paid_ad_bookings');
    }
};
