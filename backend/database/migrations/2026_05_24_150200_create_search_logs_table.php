<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('search_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('customer_id')->nullable();
            $table->string('session_id', 100);
            $table->string('query', 255);
            $table->string('query_normalized', 255);
            $table->json('filters_json');
            $table->integer('results_count');
            $table->uuid('clicked_product_id')->nullable();
            $table->integer('clicked_position')->nullable();
            $table->uuid('converted_order_id')->nullable();
            $table->string('language', 5);
            $table->uuid('country_id');
            $table->timestamp('created_at')->useCurrent();

            // $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            // $table->foreign('clicked_product_id')->references('id')->on('products')->nullOnDelete();
            // $table->foreign('converted_order_id')->references('id')->on('orders')->nullOnDelete();
            // $table->foreign('country_id')->references('id')->on('countries');

            $table->index('query_normalized');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('search_logs');
    }
};
