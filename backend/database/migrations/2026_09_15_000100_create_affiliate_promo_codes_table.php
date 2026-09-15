<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_promo_codes', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('marketer_id', 36);
            $table->string('code')->unique();
            $table->enum('type', ['percentage', 'fixed_amount'])->default('percentage');
            $table->decimal('value', 10, 2)->comment('Percentage (e.g. 10.00 = 10%) or fixed amount in currency smallest unit, depending on type');
            $table->char('currency', 3)->nullable()->comment('Null means applies regardless of order currency');
            $table->bigInteger('min_order_amount')->nullable();
            $table->bigInteger('max_discount')->nullable();
            $table->unsignedInteger('usage_limit_total')->nullable();
            $table->unsignedInteger('times_used')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->timestamps();

            $table->foreign('marketer_id')->references('id')->on('marketers')->cascadeOnDelete();
            $table->index('code');
            $table->index('is_active');
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->char('affiliate_promo_code_id', 36)->nullable()->after('coupon_id');
            $table->foreign('affiliate_promo_code_id')
                  ->references('id')->on('affiliate_promo_codes')
                  ->nullOnDelete();
            $table->index('affiliate_promo_code_id');
        });
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropForeign(['affiliate_promo_code_id']);
            $table->dropColumn('affiliate_promo_code_id');
        });

        Schema::dropIfExists('affiliate_promo_codes');
    }
};
