<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_item_custom_attribute_values', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cart_item_id')->constrained('cart_items')->cascadeOnDelete();
            $table->foreignUuid('product_custom_attribute_id')->nullable();
            $table->foreign('product_custom_attribute_id', 'cicav_product_custom_attribute_id_fk')
                ->references('id')->on('product_custom_attributes')->nullOnDelete();
            $table->string('value');
            $table->timestamps();

            $table->index('cart_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_item_custom_attribute_values');
    }
};
