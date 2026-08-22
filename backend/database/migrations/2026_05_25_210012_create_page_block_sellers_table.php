<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // Manual seller selection for seller_spotlight blocks
    public function up(): void
    {
        Schema::create('page_block_sellers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('page_block_id')->index();
            $table->uuid('seller_id')->index(); // FK → vendors
            $table->integer('position')->default(0);
            $table->timestamp('created_at')->useCurrent();
            // No updated_at

            $table->unique(['page_block_id', 'seller_id']);

            $table->foreign('page_block_id')->references('id')->on('page_blocks')->onDelete('cascade');
            $table->foreign('seller_id')->references('id')->on('vendors')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_block_sellers');
    }
};
