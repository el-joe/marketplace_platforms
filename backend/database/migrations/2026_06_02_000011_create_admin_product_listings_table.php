<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('admin_product_listings', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->foreignUuid('product_variant_id')->constrained('product_variants');
            $table->foreignUuid('country_id')->constrained('countries');
            $table->bigInteger('price_cents');
            $table->bigInteger('cost_price_cents')->nullable()->comment('Hidden from vendors/customers');
            $table->enum('commission_type', ['fixed', 'percentage', 'mixed'])->default('percentage');
            $table->decimal('commission_value', 10, 2);
            $table->char('currency', 3);
            $table->bigInteger('shipping_cost_cents')->default(0);
            $table->tinyInteger('is_exclusive')->default(0)->comment('Only admin sells this');
            $table->enum('status', ['active', 'paused', 'archived'])->default('active');
            $table->tinyInteger('available_for_vendors')->default(1);
            $table->tinyInteger('available_for_marketers')->default(1);
            $table->char('created_by_admin_id', 36)->index();
            $table->timestamps();

            $table->index(['country_id', 'status']);
            $table->index(['product_variant_id', 'country_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_product_listings');
    }
};
