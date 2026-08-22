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
         * Table: coupons ColumnTypeDescriptionidUUID PKcodeVARCHAR(50) UNIQUEWELCOME10nameVARCHAR(150)Internal labeldescriptionTEXT NULLShown to customertypeENUM('percentage','fixed_amount','free_shipping','bogo')valueDECIMAL(15,2)% or amountcurrencyCHAR(3) NULLFor fixed_amount typescopeENUM('platform','seller','category','product')seller_idUUID FK → seller_profiles.id NULLIf scope = sellercategory_idUUID FK → categories.id NULLIf scope = categorymin_order_amount_centsBIGINT NULL"Min cart 500 EGP"max_discount_centsBIGINT NULL"Max 100 EGP off" — caps % discountsusage_limit_totalINT NULLAcross all customersusage_limit_per_customerINT DEFAULT 1times_usedINT DEFAULT 0Denormalized countercustomer_eligibilityENUM('all','new_customers','specific_segment','specific_users')valid_fromTIMESTAMPvalid_untilTIMESTAMPis_activeBOOLEAN DEFAULT trueis_stackableBOOLEAN DEFAULT falseCan combine with otherscreated_by_user_idUUID FK → users.idcreated_at, updated_atTIMESTAMPS
         */
        Schema::create('coupons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->enum('type', ['percentage', 'fixed_amount', 'free_shipping', 'bogo']);
            $table->decimal('value', 15, 2);
            $table->char('currency', 3)->nullable();
            $table->enum('scope', ['platform', 'vendor', 'category', 'product']);
            $table->uuid('vendor_id')->nullable()->index();
            $table->uuid('category_id')->nullable()->index();
            $table->bigInteger('min_order_amount')->nullable();
            $table->bigInteger('max_discount')->nullable();
            $table->integer('usage_limit_total')->nullable();
            $table->integer('usage_limit_per_customer')->default(1);
            $table->integer('times_used')->default(0);
            $table->enum('customer_eligibility', ['all', 'new_customers', 'specific_segment', 'specific_users']);
            $table->timestamp('valid_from');
            $table->timestamp('valid_until');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_stackable')->default(false);
            $table->uuid('created_by_user_id')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
