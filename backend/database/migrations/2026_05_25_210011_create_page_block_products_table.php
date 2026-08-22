<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // Manual product selection for product_row / flash_sale blocks
    // Separate from JSONB config so you can query "which pages feature this product"
    public function up(): void
    {
        Schema::create('page_block_products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('page_block_id')->index();
            $table->uuid('product_variant_id')->index();
            $table->integer('position')->default(0);
            $table->uuid('added_by_admin_id')->index();
            $table->timestamp('created_at')->useCurrent();
            // No updated_at

            $table->unique(['page_block_id', 'product_variant_id']);

            $table->foreign('page_block_id')->references('id')->on('page_blocks')->onDelete('cascade');
            $table->foreign('product_variant_id')->references('id')->on('product_variants')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_block_products');
    }
};
