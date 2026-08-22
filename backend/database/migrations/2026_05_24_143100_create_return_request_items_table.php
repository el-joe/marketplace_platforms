<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('return_request_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('return_request_id')->index();
            $table->uuid('order_item_id')->index();
            $table->integer('quantity');
            $table->enum('condition_received', ['new', 'opened', 'used', 'damaged'])->nullable();
            $table->enum('restock_decision', ['restock', 'dispose', 'return_to_seller', 'liquidate'])->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_request_items');
    }
};
