<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_item_custom_attribute_values', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignUuid('product_custom_attribute_id')->nullable();
            $table->foreign('product_custom_attribute_id', 'oicav_product_custom_attribute_id_fk')
                ->references('id')->on('product_custom_attributes')->nullOnDelete();
            // Snapshotted at checkout time so later edits/deletion of the attribute
            // definition never change historical order display.
            $table->string('label');
            $table->string('unit')->nullable();
            $table->string('value');
            $table->timestamps();

            $table->index('order_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_custom_attribute_values');
    }
};
