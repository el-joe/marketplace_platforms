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
        Schema::create('warehouse_vendor_limits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('warehouse_id')->index();
            $table->uuid('vendor_id')->index();
            $table->enum('limit_type', ['quantity', 'capacity']);
            $table->integer('max_quantity')->nullable();
            $table->decimal('max_capacity_m3', 10, 2)->nullable();
            $table->timestamps();

            $table->unique(['warehouse_id', 'vendor_id']);
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->cascadeOnDelete();
            $table->foreign('vendor_id')->references('id')->on('vendors')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_vendor_limits');
    }
};
