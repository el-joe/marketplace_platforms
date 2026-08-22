<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // Manual category selection for category_pills blocks
    public function up(): void
    {
        Schema::create('page_block_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('page_block_id')->index();
            $table->uuid('category_id')->index();
            $table->integer('position')->default(0);
            $table->timestamp('created_at')->useCurrent();
            // No updated_at

            $table->unique(['page_block_id', 'category_id']);

            $table->foreign('page_block_id')->references('id')->on('page_blocks')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_block_categories');
    }
};
