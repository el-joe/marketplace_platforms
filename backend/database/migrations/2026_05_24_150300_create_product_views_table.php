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
        Schema::create('product_views', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id');
            $table->uuid('customer_id')->nullable();
            $table->string('session_id', 100);
            $table->enum('source', ['search', 'category', 'recommendation', 'direct', 'ad', 'social']);
            $table->text('referrer_url')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            // $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();

            $table->index('product_id');
            $table->index('customer_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_views');
    }
};
