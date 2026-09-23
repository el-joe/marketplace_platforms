<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketer_ad_packages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->text('description_ar')->nullable();
            $table->unsignedBigInteger('price')->comment('BIGINT base-currency. No /100.');
            $table->string('currency', 3);
            $table->unsignedTinyInteger('vat_pct')->default(15)->comment('VAT percentage 0-100');
            $table->enum('target_type', ['influencer', 'affiliate', 'broker', 'all'])->default('all');
            $table->unsignedInteger('duration_days');
            $table->json('features')->nullable()->comment('Array of feature strings shown in package UI');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketer_ad_packages');
    }
};
