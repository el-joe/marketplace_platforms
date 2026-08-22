<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('category_shipping_methods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('category_id')
                ->constrained('categories')->cascadeOnDelete();
            $table->foreignUuid('shipping_method_id')
                ->constrained('shipping_methods')->cascadeOnDelete();
            $table->boolean('is_default')->default(false)
                ->comment('Primary method shown as the main delivery badge on listing cards — one per category');
            $table->boolean('is_available_for_express_fbn')->default(true)
                ->comment('Offered for express_fbn (platform-warehoused) listings in this category');
            $table->boolean('is_available_for_merchant_fbp')->default(false)
                ->comment('Offered for merchant_fbp (vendor-fulfilled) listings — typically scheduled/pickup only');
            $table->timestamps();

            $table->unique(['category_id', 'shipping_method_id']);
            $table->index(['category_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_shipping_methods');
    }
};
