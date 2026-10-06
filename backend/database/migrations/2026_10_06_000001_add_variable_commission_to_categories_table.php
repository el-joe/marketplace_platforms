<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_commission_tiers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('category_id');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
            $table->unsignedBigInteger('price_from')->default(0)
                ->comment('Unit price lower bound (inclusive) in piastres');
            $table->unsignedBigInteger('price_to')->nullable()->default(null)
                ->comment('Unit price upper bound (inclusive) in piastres; NULL = open-ended');
            $table->decimal('commission_rate', 5, 2)->default(0.00)
                ->comment('Percentage commission rate for this tier');
            $table->unsignedBigInteger('min_commission')->default(0)
                ->comment('Per-unit floor in piastres; final = max(calculated, this * qty)');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['category_id', 'sort_order'], 'category_commission_tiers_category_sort_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_commission_tiers');
    }
};
