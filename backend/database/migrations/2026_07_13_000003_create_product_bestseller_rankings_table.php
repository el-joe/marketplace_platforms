<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_bestseller_rankings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id');
            $table->uuid('category_id');
            $table->uuid('country_id');
            $table->unsignedInteger('rank');
            $table->unsignedInteger('units_sold')->default(0);
            $table->enum('period', ['daily', 'weekly', 'monthly'])->default('monthly');
            $table->timestamp('calculated_at')->useCurrent();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
            $table->foreign('country_id')->references('id')->on('countries')->onDelete('cascade');

            $table->unique(['product_id', 'category_id', 'country_id', 'period'], 'uq_ranking');
            $table->index(['category_id', 'country_id', 'rank'], 'idx_category_country_rank');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_bestseller_rankings');
    }
};
