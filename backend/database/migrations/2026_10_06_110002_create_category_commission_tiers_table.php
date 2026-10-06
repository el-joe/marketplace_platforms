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
            $table->foreignUuid('category_id')->constrained('categories')->cascadeOnDelete();
            $table->bigInteger('price_from')->default(0)
                ->comment('Inclusive lower bound, base-currency BIGINT.');
            $table->bigInteger('price_to')->nullable()->default(null)
                ->comment('Exclusive upper bound; NULL = no upper bound.');
            $table->decimal('commission_rate', 5, 2)->default('0.00')
                ->comment('Commission % applied when unit price falls in this tier.');
            $table->bigInteger('min_commission')->default(0)
                ->comment('Per-unit minimum commission floor, base-currency BIGINT. 0 = no floor.');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['category_id', 'price_from'], 'idx_tier_category_price');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_commission_tiers');
    }
};
