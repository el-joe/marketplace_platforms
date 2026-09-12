<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_item_custom_inputs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // No FK: order_items itself avoids FK constraints on hot order columns.
            $table->uuid('order_item_id');
            $table->foreignUuid('vendor_listing_custom_field_id')->nullable()
                  ->constrained('vendor_listing_custom_fields')->nullOnDelete();
            $table->foreignUuid('vendor_listing_addon_option_id')->nullable()
                  ->constrained('vendor_listing_addon_options')->nullOnDelete();
            $table->string('input_type')
                  ->comment('"custom_field", "addon", or "order_note"');
            $table->string('label_en')->nullable();
            $table->string('label_ar')->nullable();
            $table->text('value_text')->nullable()
                  ->comment('Customer-entered value for custom fields / order notes');
            $table->bigInteger('extra_price')->nullable()
                  ->comment('Snapshot of the addon option extra price at order time, BASE CURRENCY CENTS/FILS. No decimals.');
            $table->timestamps();

            $table->index('order_item_id');
            $table->index(['order_item_id', 'input_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_custom_inputs');
    }
};
