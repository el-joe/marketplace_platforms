<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classified_wanted_listings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('listing_number', 30)->unique();
            $table->uuid('marketer_id');
            $table->uuid('classified_category_id');
            $table->uuid('country_id');
            $table->uuid('city_id')->nullable();
            $table->string('title_ar');
            $table->string('title_en')->nullable();
            $table->text('description_ar')->nullable();
            $table->bigInteger('budget_min')->nullable()->unsigned()->comment('BIGINT base-currency. No /100.');
            $table->bigInteger('budget_max')->nullable()->unsigned()->comment('BIGINT base-currency. No /100.');
            $table->string('currency', 3);
            $table->enum('status', ['active', 'fulfilled', 'cancelled', 'expired'])->default('active');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('marketer_id')->references('id')->on('marketers')->onDelete('cascade');
            $table->foreign('classified_category_id')->references('id')->on('classified_categories')->onDelete('cascade');
            $table->foreign('country_id')->references('id')->on('countries');
            $table->foreign('city_id')->references('id')->on('cities')->onDelete('set null');
            $table->index(['marketer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classified_wanted_listings');
    }
};
