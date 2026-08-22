<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('frequently_bought_together_items', function (Blueprint $table): void {
            $table->uuid('product_id')->index();
            $table->uuid('related_product_id')->index();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('related_product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->unique(['product_id', 'related_product_id'],'freq_b_t_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('frequently_bought_together_items');
    }
};
