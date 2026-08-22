<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->string('bank_name', 100)->nullable()->after('description');
            $table->string('title_ar', 255)->nullable()->after('bank_name');
            $table->string('title_en', 255)->nullable()->after('title_ar');
            $table->json('terms_ar')->nullable()->after('title_en');
            $table->json('terms_en')->nullable()->after('terms_ar');
            $table->unsignedInteger('max_orders_per_customer_per_month')->nullable()->after('usage_limit_per_customer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn([
                'bank_name',
                'title_ar',
                'title_en',
                'terms_ar',
                'terms_en',
                'max_orders_per_customer_per_month',
            ]);
        });
    }
};
