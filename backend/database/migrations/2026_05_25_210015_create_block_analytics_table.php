<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // Aggregated block metrics per day — high-write table
    // Raw events flow through Redis counters; a job flushes here every 5 minutes
    public function up(): void
    {
        Schema::create('block_analytics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('page_block_id')->index();
            $table->uuid('page_id')->index();    // denormalized
            $table->uuid('country_id')->index(); // denormalized
            $table->date('date');
            $table->integer('impressions')->default(0);
            $table->integer('clicks')->default(0);
            $table->integer('unique_visitors')->default(0);
            $table->integer('add_to_cart_count')->default(0);
            $table->integer('orders_attributed')->default(0);
            $table->bigInteger('revenue_attributed_cents')->default(0);
            $table->decimal('ctr', 6, 4)->default(0); // clicks / impressions
            $table->timestamps();

            $table->unique(['page_block_id', 'date']);
            $table->index(['page_id', 'date']);
            $table->index(['country_id', 'date']);
            $table->index('date');

            $table->foreign('page_block_id')->references('id')->on('page_blocks')->onDelete('cascade');
            $table->foreign('page_id')->references('id')->on('pages')->onDelete('cascade');
            $table->foreign('country_id')->references('id')->on('countries')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('block_analytics');
    }
};
