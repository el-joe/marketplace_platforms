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
         * carts ColumnTypeNullDescriptionidUUID PKNOcountry_idUUID FK→countriesNOWhich country site cart was created onuser_idUUID FK→usersYESNULL for guestssession_tokenVARCHAR(100)YESGuest cart identifiercurrencyCHAR(3)NODerived from countrycoupon_idUUID FK→couponsYESsubtotal_centsBIGINTNOdiscount_centsBIGINTNOestimated_shipping_centsBIGINTNOestimated_tax_centsBIGINTNOestimated_total_centsBIGINTNOexpires_atTIMESTAMPTZYESGuest carts expire after 30 dayscreated_atTIMESTAMPTZNOupdated_atTIMESTAMPTZNO
         */
        Schema::create('carts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('country_id');
            $table->uuid('user_id')->nullable()->index();
            $table->string('session_token', 100)->nullable();
            $table->char('currency', 3);
            $table->uuid('coupon_id')->nullable()->index();
            $table->bigInteger('subtotal');
            $table->bigInteger('discount');
            $table->bigInteger('estimated_shipping');
            $table->bigInteger('estimated_tax');
            $table->bigInteger('estimated_total');
            $table->timestampTz('expires_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
