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
        Schema::create('open_market_listing_prices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('classified_category_id')
                ->comment('The open-market (classified) category this base price applies to.');
            $table->unsignedBigInteger('base_price')
                ->comment('Base listing price (base currency unit) for this classified category.');
            $table->boolean('allow_marketer_override')->default(false)
                ->comment('When true, the marketer may set their own price for listings in this category, bounded by min_price/max_price if set.');
            $table->unsignedBigInteger('min_price')->nullable()
                ->comment('Lower bound for a marketer-overridden price, when allow_marketer_override is true.');
            $table->unsignedBigInteger('max_price')->nullable()
                ->comment('Upper bound for a marketer-overridden price, when allow_marketer_override is true.');
            $table->uuid('updated_by_admin_id')->nullable();
            $table->timestamps();

            $table->unique('classified_category_id', 'omlp_classified_category_unique');

            $table->foreign('classified_category_id')->references('id')->on('classified_categories')->cascadeOnDelete();
            $table->foreign('updated_by_admin_id')->references('id')->on('admins')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('open_market_listing_prices');
    }
};
