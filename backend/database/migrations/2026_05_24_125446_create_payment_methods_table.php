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
         * Table: payment_methods Saved payment methods per customer. ColumnTypeDescriptionidUUID PKuser_idUUID FK → users.idtypeENUM('card','wallet','bank')gatewayVARCHAR(50)stripe, paymob, paytabsgateway_tokenVARCHAR(255)Tokenized — NEVER raw PANcard_brandVARCHAR(20) NULL"Visa", "Mastercard"card_last4CHAR(4) NULLcard_exp_monthTINYINT NULLcard_exp_yearSMALLINT NULLbilling_address_idUUID FK → addresses.id NULLis_defaultBOOLEAN DEFAULT falsecreated_at, updated_at, deleted_atTIMESTAMPS
         */
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('customer_id')->index();
            $table->enum('type', ['card', 'wallet', 'bank']);
            $table->string('gateway', 50);
            $table->string('gateway_token', 255);
            $table->string('card_brand', 20)->nullable();
            $table->char('card_last4', 4)->nullable();
            $table->tinyInteger('card_exp_month')->nullable();
            $table->smallInteger('card_exp_year')->nullable();
            $table->uuid('billing_address_id')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
